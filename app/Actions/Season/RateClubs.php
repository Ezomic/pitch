<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Squad;
use App\Models\Team;
use App\Sim\Squad\ClubStrength;
use App\Sim\Squad\StarRating;

/**
 * Rate every senior club twice: against the whole world, and against the clubs it
 * shares a division with. The manager's own squad is ranked alongside the rest, so
 * the table shows where they really stand.
 */
class RateClubs
{
    /** The manager's own club is keyed this way, matching the standings table. */
    public const string USER_KEY = 'user';

    public function __construct(
        private readonly ClubStrength $strength = new ClubStrength,
        private readonly StarRating $stars = new StarRating,
    ) {}

    /**
     * @return array<array-key, array{strength: float, world: float, league: float, division: int}>
     */
    public function handle(?Squad $squad = null): array
    {
        /** @var array<array-key, array{strength: float, division: int}> $clubs */
        $clubs = [];

        foreach (Team::query()->where('is_youth', false)->get() as $team) {
            $clubs[$team->id] = [
                'strength' => $this->strength->of($team->setup()),
                'division' => $team->division,
            ];
        }

        if ($squad !== null) {
            $clubs[self::USER_KEY] = [
                'strength' => $this->strength->of($squad->setup()),
                'division' => $squad->division,
            ];
        }

        if ($clubs === []) {
            return [];
        }

        $world = $this->stars->rank(array_map(fn (array $c): float => $c['strength'], $clubs));

        // Each division is ranked on its own, so a mid-table side in a strong league
        // is not flattered by the company it keeps.
        $league = [];
        foreach ($this->byDivision($clubs) as $group) {
            $league += $this->stars->rank($group);
        }

        $rated = [];
        foreach ($clubs as $key => $club) {
            $rated[$key] = [
                'strength' => round($club['strength'], 1),
                'world' => $world[$key],
                'league' => $league[$key],
                'division' => $club['division'],
            ];
        }

        return $rated;
    }

    /**
     * @param  array<array-key, array{strength: float, division: int}>  $clubs
     * @return array<int, array<array-key, float>>
     */
    private function byDivision(array $clubs): array
    {
        $groups = [];
        foreach ($clubs as $key => $club) {
            $groups[$club['division']][$key] = $club['strength'];
        }

        return $groups;
    }
}
