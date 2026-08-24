<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Team;
use Carbon\CarbonImmutable;

/**
 * Lay out a season's fixtures: a full double round-robin for the seniors (the
 * user, a null team id, plays every rival home and away) plus a one-off youth
 * league. Shared by the first-season setup and the season rollover.
 */
class ScheduleSeason
{
    /**
     * A bye: an odd number of sides needs an extra chair at the table, and
     * whoever is drawn against it sits the matchday out.
     */
    private const int BYE = -1;

    public function handle(Season $season): void
    {
        $teamIds = array_values(array_map('intval', Team::query()
            ->where('is_youth', false)
            ->where('division', $season->division)
            ->orderBy('id')->pluck('id')->all()));
        $this->generateSchedule($season, array_merge([null], $teamIds));

        $youthTeamIds = array_values(array_map('intval', Team::query()->where('is_youth', true)->orderBy('id')->pluck('id')->all()));
        $this->generateYouthSchedule($season, $youthTeamIds);
    }

    /**
     * A league's fixture list: every senior club in the world plays every other
     * home and away. Nobody is the null side here, because a league has several
     * managers and each of them runs a real club.
     */
    public function handleLeague(Season $season): void
    {
        $teamIds = array_values(array_map('intval', Team::query()
            ->where('is_youth', false)
            ->orderBy('id')->pluck('id')->all()));

        if (count($teamIds) % 2 === 1) {
            $teamIds[] = self::BYE;
        }

        $this->generateSchedule($season, $teamIds);
    }

    /**
     * @param  list<int|null>  $participants
     */
    private function generateSchedule(Season $season, array $participants): void
    {
        $count = count($participants);
        $rounds = $count - 1;
        $half = intdiv($count, 2);

        $fixed = $participants[0];
        $rotating = array_slice($participants, 1);

        $firstHalf = [];
        for ($round = 0; $round < $rounds; $round++) {
            $ordered = array_merge([$fixed], $rotating);
            $day = [];

            for ($i = 0; $i < $half; $i++) {
                $a = $ordered[$i];
                $b = $ordered[$count - 1 - $i];
                $day[] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
            }

            $firstHalf[] = $day;
            array_unshift($rotating, array_pop($rotating));
        }

        $index = 0;
        foreach ($firstHalf as $matchday => $day) {
            foreach ($day as [$home, $away]) {
                $this->createFixture($season, $matchday + 1, $home, $away, $index++);
            }
        }

        foreach ($firstHalf as $matchday => $day) {
            foreach ($day as [$home, $away]) {
                $this->createFixture($season, $rounds + $matchday + 1, $away, $home, $index++);
            }
        }
    }

    private function createFixture(Season $season, int $matchday, ?int $home, ?int $away, int $index): void
    {
        // Drawn against the bye: no fixture, but the seed index still moves on so
        // every other match keeps the seed it would have had.
        if ($home === self::BYE || $away === self::BYE) {
            return;
        }

        $season->fixtures()->create([
            'matchday' => $matchday,
            'scheduled_on' => CarbonImmutable::parse($season->starts_on)->addWeeks($matchday),
            'home_team_id' => $home,
            'away_team_id' => $away,
            'seed' => $season->id * 1000 + $index + 1,
            'played' => false,
        ]);
    }

    /**
     * @param  list<int>  $youthTeamIds
     */
    private function generateYouthSchedule(Season $season, array $youthTeamIds): void
    {
        foreach ($youthTeamIds as $matchday => $teamId) {
            $season->fixtures()->create([
                'matchday' => $matchday + 1,
                'youth' => true,
                'scheduled_on' => CarbonImmutable::parse($season->starts_on)->addWeeks($matchday + 1),
                'home_team_id' => null,
                'away_team_id' => $teamId,
                'seed' => $season->id * 1000 + 900 + $matchday,
                'played' => false,
            ]);
        }
    }
}
