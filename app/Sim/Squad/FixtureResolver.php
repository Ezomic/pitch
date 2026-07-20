<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Engine\MatchEngine;

final class FixtureResolver
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
    ) {}

    /**
     * Resolve a single fixture between two teams: each side attacks the other's
     * defence for the same seed, using its own formation and mentality.
     * Deterministic.
     *
     * @return array{home: int, away: int}
     */
    public function resolve(TeamSetup $home, TeamSetup $away, int $seed): array
    {
        $homeGoals = $this->engine->simulate(
            $home->attackers(),
            $seed,
            $away->defence(),
            $home->formation,
            $home->attackBias(),
        )->goals;

        $awayGoals = $this->engine->simulate(
            $away->attackers(),
            $seed,
            $home->defence(),
            $away->formation,
            $away->attackBias(),
        )->goals;

        return ['home' => $homeGoals, 'away' => $awayGoals];
    }
}
