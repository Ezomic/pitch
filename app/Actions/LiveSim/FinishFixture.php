<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Actions\News\RecordNews;
use App\Actions\Season\ApplyMatchCondition;
use App\Actions\Season\RecordSeasonContribution;
use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\News;
use App\Models\Player;
use App\Models\SquadPlayer;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

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
        private readonly RecordSeasonContribution $recordContribution = new RecordSeasonContribution,
    ) {}

    public function handle(LiveMatch $match): void
    {
        $fixture = $match->fixture;

        // A friendly counts for nothing, so it never costs a player weeks out either.
        // A settled fixture is left alone so a repeated final slice cannot count twice.
        if (! $fixture instanceof Fixture || $fixture->played) {
            return;
        }

        DB::transaction(fn () => $this->settle($match, $fixture));
    }

    private function settle(LiveMatch $match, Fixture $fixture): void
    {
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

        // Fold this match into the season's record while it still exists: live
        // matches are pruned a week after they finish.
        $this->recordContribution->handle($fixture->season, $match);

        $this->applyMatchCondition->handle(
            $this->lineup($match),
            $match->home_goals,
            $match->away_goals,
            $this->scorerIds($match),
            $opponent instanceof Team && $opponent->is_derby ? 2 : 1,
        );

        $this->carryInjuries($match);
    }

    /**
     * The players hurt in the match miss the weeks it cost them, and drop out of
     * the XI like any other injured man. A longer absence already running is kept.
     */
    private function carryInjuries(LiveMatch $match): void
    {
        foreach ($match->injuries ?? [] as $injury) {
            $player = Player::query()->find($injury['pid']);

            if (! $player instanceof Player) {
                continue;
            }

            $player->forceFill(['injured_weeks' => max($player->injured_weeks, $injury['weeks'])])->save();
            SquadPlayer::query()->where('player_id', $player->id)->delete();
        }
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
