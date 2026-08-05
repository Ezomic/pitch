<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Squad\TeamSetup;

/**
 * Begin a live positional match for the user's squad against an opponent. The
 * kickoff engine state (and its Rng position) are serialised onto the LiveMatch
 * so the tick loop can be advanced a slice at a time across requests.
 *
 * A match tied to a league fixture takes that fixture's stored seed, so it is
 * reproducible from persisted state and the result written back at full time
 * belongs to a match anyone could re-run. A friendly draws its own seed.
 */
class StartMatch
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly LivePitch $live = new LivePitch,
    ) {}

    /** A friendly against a club from the squad's division. Counts for nothing. */
    public function handle(User $user, Squad $squad): LiveMatch
    {
        return $this->begin($user, $squad, $this->sparringPartner($squad), null);
    }

    /** The manager's own league fixture, played out for real. */
    public function forFixture(User $user, Squad $squad, Fixture $fixture): LiveMatch
    {
        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;

        return $this->begin($user, $squad, Team::find($opponentId), $fixture);
    }

    private function begin(User $user, Squad $squad, ?Team $team, ?Fixture $fixture): LiveMatch
    {
        // Kicking off a new match walks away from whatever was still running, so
        // mark it abandoned rather than leaving a second 'live' row behind: only
        // one match is ever in progress, and the pruner knows what to clear up.
        LiveMatch::query()
            ->where('user_id', $user->id)
            ->where('status', LiveMatch::LIVE)
            ->update(['status' => LiveMatch::ABANDONED]);

        $setup = $squad->setup();
        $opponent = $team !== null ? $team->setup() : TeamSetup::baseline();

        $names = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $names[$assignment->slot] = ['id' => $assignment->player->id, 'name' => $assignment->player->name];
        }
        foreach ($setup->formation->slots() as $slot) {
            $names[$slot] ??= ['id' => null, 'name' => "Slot {$slot}"];
        }

        // The engine's side 0 is always the manager's side, whichever end of the
        // fixture they are. Orientation is applied when the score is written back.
        $seed = $fixture instanceof Fixture ? $fixture->seed : random_int(1, 2_000_000_000);
        [$state, $rng] = $this->engine->start($setup->attackers(), $opponent->attackers(), $seed);

        return LiveMatch::create([
            'user_id' => $user->id,
            'fixture_id' => $fixture?->id,
            'seed' => $seed,
            'current_tick' => 0,
            'total_ticks' => $this->engine->totalTicks(),
            'pitch_state' => $state->toSnapshot(),
            'kickoff_state' => $state->toSnapshot(),
            'rng_state' => $rng->stateValue(),
            'home_goals' => 0,
            'away_goals' => 0,
            'home_name' => $squad->name ?? 'Your squad',
            'away_name' => $team instanceof Team ? $team->name : 'Opposition',
            'opponent_team_id' => $team?->id,
            'players' => $this->live->players($names),
            'moments' => [],
            'scorers' => [],
            'interventions' => [],
            'subs_remaining' => 5,
            'status' => LiveMatch::LIVE,
        ]);
    }

    /**
     * A senior club from the squad's own division to play a friendly against,
     * chosen at random so the opponent (and with it the formation, mentality and
     * quality) changes between matches. Falls back to any senior club, then to
     * the sparring baseline when no clubs are seeded at all.
     */
    private function sparringPartner(Squad $squad): ?Team
    {
        return Team::query()->where('is_youth', false)->where('division', $squad->division)->inRandomOrder()->first()
            ?? Team::query()->where('is_youth', false)->inRandomOrder()->first();
    }
}
