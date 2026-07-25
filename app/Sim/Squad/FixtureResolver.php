<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\SetPieces;

final class FixtureResolver
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly SetPieces $setPieces = new SetPieces,
    ) {}

    /**
     * Resolve a single fixture between two teams: each side attacks the other's
     * defence for the same seed, using its own formation and mentality, with a
     * set-piece phase added on top. Deterministic.
     *
     * @return array{home: int, away: int}
     */
    public function resolve(TeamSetup $home, TeamSetup $away, int $seed): array
    {
        $homeOpen = $this->engine->simulate(
            $home->attackers(),
            $seed,
            $away->defence(),
            $home->formation,
            $home->attackBias(),
        );

        $awayOpen = $this->engine->simulate(
            $away->attackers(),
            $seed,
            $home->defence(),
            $away->formation,
            $away->attackBias(),
        );

        $homeSet = $this->setPieces->resolve($home->setPiece, $away->defence(), $seed, $homeOpen->shots);
        $awaySet = $this->setPieces->resolve($away->setPiece, $home->defence(), $seed + 1, $awayOpen->shots);

        return [
            'home' => $homeOpen->goals + $homeSet['goals'],
            'away' => $awayOpen->goals + $awaySet['goals'],
        ];
    }
}
