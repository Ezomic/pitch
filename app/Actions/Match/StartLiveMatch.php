<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Actions\Squad\EnsureSquad;
use App\Models\Fixture;
use App\Models\MatchSession;
use App\Models\Team;
use App\Models\User;

/**
 * Begin (or resume) the live playthrough of the user's pending fixture. The whole
 * match is simulated up front into a minute-ordered moment feed; the client paces
 * it out on a clock. Resuming returns the existing session unchanged.
 */
class StartLiveMatch
{
    public function __construct(
        private readonly SimulateLiveMatch $simulate = new SimulateLiveMatch,
        private readonly EnsureSquad $ensureSquad = new EnsureSquad,
    ) {}

    public function handle(User $user, Fixture $fixture): MatchSession
    {
        $existing = MatchSession::query()
            ->where('user_id', $user->id)
            ->where('fixture_id', $fixture->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $squad = $this->ensureSquad->handle($user);

        $lineup = [];
        $names = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $lineup[$assignment->slot] = $assignment->player_id;
            $names[$assignment->slot] = $assignment->player->name;
        }

        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;
        $opponent = Team::findOrFail($opponentId);

        $result = $this->simulate->handle(
            $squad->setupFrom($lineup), $names, $opponent->setup(), $opponent->name, $fixture->seed,
        );

        return MatchSession::create([
            'user_id' => $user->id,
            'fixture_id' => $fixture->id,
            'seed' => $fixture->seed,
            'home_goals' => $result['scored'],
            'away_goals' => $result['conceded'],
            'moments' => $result['moments'],
            'lineup' => $lineup,
            'bench' => [],
            'scorers' => ResolveScorers::forLineup($result['scorers'], $lineup),
            'subs_remaining' => 3,
            'status' => 'in_progress',
        ]);
    }
}
