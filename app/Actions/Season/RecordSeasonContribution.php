<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\LiveMatch;
use App\Models\Season;
use App\Sim\Analysis\MatchRatings;

/**
 * Fold a finished league match into the season's running record of who did what.
 *
 * This has to happen as each match ends rather than when the season closes.
 * Live matches are pruned a week after they finish, so by the time a campaign
 * is over its early matches no longer exist: a season review computed at the
 * end would only ever describe the last few weeks.
 *
 * Only the manager's own players are recorded. Rival fixtures are resolved by
 * FixtureResolver, which returns a scoreline and nothing else, so no NPC club
 * has named scorers to rank.
 */
class RecordSeasonContribution
{
    public function __construct(
        private readonly MatchRatings $ratings = new MatchRatings,
    ) {}

    public function handle(Season $season, LiveMatch $match): void
    {
        $summary = $match->summary;

        if ($summary === null) {
            return;
        }

        $names = $this->names($match);
        $ratings = $this->ratings->forMatch($summary);
        $review = $season->review ?? ['players' => []];

        foreach ($summary['players'] ?? [] as $who => $counters) {
            if (! isset($names[$who])) {
                continue; // the opposition, who are unnamed
            }

            $row = $review['players'][$who] ?? [
                'name' => $names[$who],
                'appearances' => 0,
                'goals' => 0,
                'ratingSum' => 0.0,
            ];

            // A name can change hands over a career; the latest is the one to keep.
            $row['name'] = $names[$who];
            $row['appearances']++;
            $row['goals'] += (int) ($counters['goals'] ?? 0);
            $row['ratingSum'] += (float) ($ratings[$who] ?? 0.0);

            $review['players'][$who] = $row;
        }

        $season->forceFill(['review' => $review])->save();
    }

    /**
     * @return array<int, string>
     */
    private function names(LiveMatch $match): array
    {
        $names = [];
        foreach ($match->players as $player) {
            if ((int) $player['s'] === 0 && ($player['pid'] ?? null) !== null) {
                $names[(int) $player['pid']] = $player['name'] ?? 'Unknown';
            }
        }

        return $names;
    }
}
