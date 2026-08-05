<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

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
 */
class StartMatch
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly LivePitch $live = new LivePitch,
    ) {}

    public function handle(User $user, Squad $squad): LiveMatch
    {
        // Kicking off a new match walks away from whatever was still running, so
        // mark it abandoned rather than leaving a second 'live' row behind: only
        // one match is ever in progress, and the pruner knows what to clear up.
        LiveMatch::query()
            ->where('user_id', $user->id)
            ->where('status', LiveMatch::LIVE)
            ->update(['status' => LiveMatch::ABANDONED]);

        $setup = $squad->setup();

        // Face a real club rather than one hardcoded sparring partner: each has its
        // own formation, mentality and ratings, so the shape across the pitch and
        // the level of the test change from match to match.
        $team = $this->opponentFor($squad);
        $opponent = $team !== null ? $team->setup() : TeamSetup::baseline();
        $awayName = $team !== null ? $team->name : 'Opposition';

        $names = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $names[$assignment->slot] = ['id' => $assignment->player->id, 'name' => $assignment->player->name];
        }
        foreach ($setup->formation->slots() as $slot) {
            $names[$slot] ??= ['id' => null, 'name' => "Slot {$slot}"];
        }

        $seed = random_int(1, 2_000_000_000);
        [$state, $rng] = $this->engine->start($setup->attackers(), $opponent->attackers(), $seed);

        return LiveMatch::create([
            'user_id' => $user->id,
            'seed' => $seed,
            'current_tick' => 0,
            'total_ticks' => $this->engine->totalTicks(),
            'pitch_state' => $state->toSnapshot(),
            'rng_state' => $rng->stateValue(),
            'home_goals' => 0,
            'away_goals' => 0,
            'home_name' => 'Your squad',
            'away_name' => $awayName,
            'opponent_team_id' => $team?->id,
            'players' => $this->live->players($names),
            'moments' => [],
            'subs_remaining' => 5,
            'status' => LiveMatch::LIVE,
        ]);
    }

    /**
     * A senior club from the squad's own division to play, chosen at random so the
     * opponent (and with it the formation, mentality and quality) changes between
     * matches. Falls back to any senior club, then to the sparring baseline when no
     * clubs are seeded at all.
     */
    private function opponentFor(Squad $squad): ?Team
    {
        return Team::query()->where('is_youth', false)->where('division', $squad->division)->inRandomOrder()->first()
            ?? Team::query()->where('is_youth', false)->inRandomOrder()->first();
    }
}
