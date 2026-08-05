<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Actions\News\RecordNews;
use App\Actions\Season\ApplyMatchCondition;
use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\News;
use App\Models\Team;

/**
 * Blow the full-time whistle on a league match: write the score that was
 * actually played out onto the fixture so it counts in the standings, credit
 * the goals, and settle the eleven's fitness and form from the result.
 *
 * The engine always runs the manager's side as side 0, so the score is mapped
 * to the fixture's home/away orientation here rather than in the engine.
 */
class FinishFixture
{
    public function __construct(
        private readonly ApplyMatchCondition $applyMatchCondition = new ApplyMatchCondition,
        private readonly RecordNews $recordNews = new RecordNews,
    ) {}

    public function handle(LiveMatch $match): void
    {
        $fixture = $match->fixture;

        // Nothing to write back for a friendly, and a fixture already settled is
        // left alone so a repeated final slice cannot count twice.
        if (! $fixture instanceof Fixture || $fixture->played) {
            return;
        }

        [$homeGoals, $awayGoals] = $fixture->userIsHome()
            ? [$match->home_goals, $match->away_goals]
            : [$match->away_goals, $match->home_goals];

        $fixture->update([
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'played' => true,
        ]);

        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;
        $opponent = Team::find($opponentId);

        $this->recordResult($match, $fixture, $opponent instanceof Team ? $opponent->name : 'a rival');

        $this->applyMatchCondition->handle(
            $this->lineup($match),
            $match->home_goals,
            $match->away_goals,
            $this->scorerIds($match),
            $opponent instanceof Team && $opponent->is_derby ? 2 : 1,
        );
    }

    /**
     * The squad players who were on the pitch at full time.
     *
     * @return list<int>
     */
    private function lineup(LiveMatch $match): array
    {
        $ids = [];
        foreach ($match->players as $meta) {
            if ((int) $meta['s'] === 0 && ($meta['pid'] ?? null) !== null) {
                $ids[] = (int) $meta['pid'];
            }
        }

        return $ids;
    }

    /**
     * The players who scored, resolved from the slot that struck each goal. A
     * slot with no real player behind it is dropped.
     *
     * @return list<int>
     */
    private function scorerIds(LiveMatch $match): array
    {
        $bySlot = [];
        foreach ($match->players as $meta) {
            if ((int) $meta['s'] === 0) {
                $bySlot[(int) $meta['slot']] = $meta['pid'] ?? null;
            }
        }

        $ids = [];
        foreach ($match->scorers ?? [] as $scorer) {
            $playerId = $bySlot[(int) $scorer['slot']] ?? null;

            if ($playerId !== null) {
                $ids[] = (int) $playerId;
            }
        }

        return $ids;
    }

    private function recordResult(LiveMatch $match, Fixture $fixture, string $opponentName): void
    {
        $userGoals = $match->home_goals;
        $oppGoals = $match->away_goals;

        $verb = match (true) {
            $userGoals > $oppGoals => 'Won',
            $userGoals < $oppGoals => 'Lost',
            default => 'Drew',
        };

        $this->recordNews->handle(
            userId: $match->user_id,
            category: News::RESULT,
            title: $verb.' '.$userGoals.'-'.$oppGoals.' '.($fixture->userIsHome() ? 'vs' : 'at').' '.$opponentName,
            body: 'Your side '.strtolower($verb).' '.$userGoals.'-'.$oppGoals.' against '.$opponentName.'.',
            seasonId: $fixture->season_id,
        );
    }
}
