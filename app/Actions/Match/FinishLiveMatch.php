<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Models\MatchSession;

/**
 * Blow the full-time whistle: write the played-out score onto the fixture (mapped
 * to home/away orientation) so it counts in the standings, and close the session.
 */
class FinishLiveMatch
{
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

        $session->delete();
    }
}
