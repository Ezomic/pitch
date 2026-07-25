<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Actions\News\RecordNews;
use App\Actions\Season\ApplyMatchCondition;
use App\Models\MatchSession;
use App\Models\News;
use App\Models\Team;

/**
 * Blow the full-time whistle: write the played-out score onto the fixture (mapped
 * to home/away orientation) so it counts in the standings, settle the XI's
 * fitness and form from the result, and close the session.
 */
class FinishLiveMatch
{
    public function __construct(
        private readonly ApplyMatchCondition $applyMatchCondition = new ApplyMatchCondition,
        private readonly RecordNews $recordNews = new RecordNews,
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

        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;
        $opponent = Team::find($opponentId);
        $stakes = $opponent instanceof Team && $opponent->is_derby ? 2 : 1;

        $opponentName = $opponent instanceof Team ? $opponent->name : 'a rival';
        $this->recordResult($session, $opponentName, $fixture->userIsHome());

        $this->applyMatchCondition->handle(
            array_values(array_map('intval', $session->lineup ?? [])),
            $session->home_goals,
            $session->away_goals,
            array_values(array_map(fn (array $scorer): int => (int) $scorer['player_id'], $session->scorers ?? [])),
            $stakes,
        );

        $session->delete();
    }

    private function recordResult(MatchSession $session, string $opponentName, bool $userIsHome): void
    {
        $userGoals = $userIsHome ? $session->home_goals : $session->away_goals;
        $oppGoals = $userIsHome ? $session->away_goals : $session->home_goals;

        $verb = match (true) {
            $userGoals > $oppGoals => 'Won',
            $userGoals < $oppGoals => 'Lost',
            default => 'Drew',
        };

        $this->recordNews->handle(
            userId: $session->user_id,
            category: News::RESULT,
            title: $verb.' '.$userGoals.'-'.$oppGoals.' '.($userIsHome ? 'vs' : 'at').' '.$opponentName,
            body: 'Your side '.strtolower($verb).' '.$userGoals.'-'.$oppGoals.' against '.$opponentName.'.',
            seasonId: $session->fixture->season_id,
        );
    }
}
