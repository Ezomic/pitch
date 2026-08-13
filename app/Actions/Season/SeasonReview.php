<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;

/**
 * How the campaign went: where the club finished, whether that met what the
 * board asked for, and who did the work.
 *
 * Read entirely off the record built up as each match finished, so it says the
 * same thing however long after the season it is asked.
 */
class SeasonReview
{
    /** Nobody is player of the season on the strength of one good afternoon. */
    private const MIN_APPEARANCES = 3;

    /**
     * @param  list<array<string, mixed>>  $table
     * @param  array{target: int, position: int, teams: int, met: bool|null}  $objective
     * @return array{
     *     position: int,
     *     teams: int,
     *     objective: array{target: int, position: int, teams: int, met: bool|null},
     *     verdict: string,
     *     topScorers: list<array{name: string, goals: int}>,
     *     mostAppearances: list<array{name: string, appearances: int}>,
     *     playerOfTheSeason: array{name: string, rating: float, appearances: int}|null
     * }
     */
    public function handle(Season $season, array $table, array $objective): array
    {
        $players = $this->players($season);

        return [
            'position' => $objective['position'],
            'teams' => $objective['teams'],
            'objective' => $objective,
            'verdict' => $this->verdict($objective),
            'topScorers' => $this->topScorers($players),
            'mostAppearances' => $this->mostAppearances($players),
            'playerOfTheSeason' => $this->playerOfTheSeason($players),
        ];
    }

    /**
     * @return list<array{name: string, appearances: int, goals: int, rating: float}>
     */
    private function players(Season $season): array
    {
        $rows = [];

        foreach (($season->review['players'] ?? []) as $row) {
            $appearances = max(1, (int) ($row['appearances'] ?? 0));

            $rows[] = [
                'name' => (string) ($row['name'] ?? 'Unknown'),
                'appearances' => (int) ($row['appearances'] ?? 0),
                'goals' => (int) ($row['goals'] ?? 0),
                'rating' => round(((float) ($row['ratingSum'] ?? 0)) / $appearances, 1),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, appearances: int, goals: int, rating: float}>  $players
     * @return list<array{name: string, goals: int}>
     */
    private function topScorers(array $players): array
    {
        $scorers = array_values(array_filter($players, fn (array $p): bool => $p['goals'] > 0));
        usort($scorers, fn (array $a, array $b): int => $b['goals'] <=> $a['goals']);

        return array_map(
            fn (array $p): array => ['name' => $p['name'], 'goals' => $p['goals']],
            array_slice($scorers, 0, 5),
        );
    }

    /**
     * @param  list<array{name: string, appearances: int, goals: int, rating: float}>  $players
     * @return list<array{name: string, appearances: int}>
     */
    private function mostAppearances(array $players): array
    {
        usort($players, fn (array $a, array $b): int => $b['appearances'] <=> $a['appearances']);

        return array_map(
            fn (array $p): array => ['name' => $p['name'], 'appearances' => $p['appearances']],
            array_slice($players, 0, 5),
        );
    }

    /**
     * @param  list<array{name: string, appearances: int, goals: int, rating: float}>  $players
     * @return array{name: string, rating: float, appearances: int}|null
     */
    private function playerOfTheSeason(array $players): ?array
    {
        $eligible = array_values(array_filter(
            $players,
            fn (array $p): bool => $p['appearances'] >= self::MIN_APPEARANCES,
        ));

        if ($eligible === []) {
            return null;
        }

        usort($eligible, fn (array $a, array $b): int => $b['rating'] <=> $a['rating']);
        $best = $eligible[0];

        return ['name' => $best['name'], 'rating' => $best['rating'], 'appearances' => $best['appearances']];
    }

    /**
     * @param  array{target: int, position: int, teams: int, met: bool|null}  $objective
     */
    private function verdict(array $objective): string
    {
        if ($objective['met'] === null) {
            return 'The season is not over yet.';
        }

        $position = $objective['position'];
        $target = $objective['target'];

        if (! $objective['met']) {
            return "Finished {$position} of {$objective['teams']}, short of the top {$target} the board asked for.";
        }

        return $position === 1
            ? 'Champions. The board asked for a top '.$target.' finish.'
            : "Finished {$position} of {$objective['teams']}, meeting the top {$target} the board asked for.";
    }
}
