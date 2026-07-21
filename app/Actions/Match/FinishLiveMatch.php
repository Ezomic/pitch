<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Actions\Season\ApplyMatchCondition;
use App\Models\MatchSession;

/**
 * Blow the full-time whistle: write the played-out score onto the fixture (mapped
 * to home/away orientation) so it counts in the standings, settle the XI's
 * fitness and form from the result, and close the session.
 */
class FinishLiveMatch
{
    public function __construct(
        private readonly ApplyMatchCondition $applyMatchCondition = new ApplyMatchCondition,
    ) {}

    public function handle(MatchSession $session): void
    {
        $fixture = $session->fixture;

        [$homeGoals, $awayGoals] = $fixture->userIsHome()
            ? [$session->home_goals, $session->away_goals]
            : [$session->away_goals, $session->home_goals];

        $fixture->update([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played' => true,
        ]);

        $this->applyMatchCondition->handle(
            array_values(array_map('intval', $session->lineup ?? [])),
            $session->home_goals,
            $session->away_goals,
            array_values(array_map(fn (array $scorer): int => (int) $scorer['player_id'], $session->scorers ?? [])),
        );

        $session->delete();
    }
}
