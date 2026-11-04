<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\Fixture;

class BuildFixtureReplay
{
    /**
     * What the replay page shows, read only from the log the runner stored with
     * the fixture. The scoreline is counted from the goal events, so it is the
     * log's own account of the match and not a copy of the fixture's columns.
     *
     * @return array{
     *     home: string|null,
     *     away: string|null,
     *     matchday: int,
     *     version: string|null,
     *     homeGoals: int,
     *     awayGoals: int,
     *     stats: list<array{label: string, home: int|float, away: int|float}>,
     *     moments: list<array{minute: int, kind: string, text: string}>
     * }
     */
    public function handle(Fixture $fixture): array
    {
        $home = $fixture->homeTeam?->name;
        $away = $fixture->awayTeam?->name;

        $goals = collect($fixture->runner_events ?? [])
            ->filter(fn (array $event): bool => ($event['type'] ?? null) === 'goal')
            ->sortBy(fn (array $event): float => (float) ($event['t'] ?? 0))
            ->values();

        return [
            'home' => $home,
            'away' => $away,
            'matchday' => $fixture->matchday,
            'version' => $fixture->runner_version,
            'homeGoals' => $goals->where('side', 'home')->count(),
            'awayGoals' => $goals->where('side', 'away')->count(),
            'stats' => $this->stats($fixture->runner_result['stats'] ?? []),
            'moments' => array_values($goals->map(fn (array $event): array => [
                'minute' => (int) floor((float) ($event['t'] ?? 0)),
                'kind' => 'goal',
                'text' => 'Goal for '.(($event['side'] ?? null) === 'home' ? $home : $away),
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return list<array{label: string, home: int|float, away: int|float}>
     */
    private function stats(array $stats): array
    {
        $rows = [];
        foreach ($stats as $name => $pair) {
            if (is_array($pair) && count($pair) === 2 && is_numeric($pair[0] ?? null) && is_numeric($pair[1] ?? null)) {
                $rows[] = [
                    'label' => ucfirst(str_replace('_', ' ', (string) $name)),
                    'home' => $pair[0] + 0,
                    'away' => $pair[1] + 0,
                ];
            }
        }

        return $rows;
    }
}
