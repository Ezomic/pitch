<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Defense;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

final class FixtureResolver
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
    ) {}

    /**
     * Resolve a single fixture between two teams: each side attacks the other's
     * defence for the same seed. Deterministic.
     *
     * @param  array<int, Attributes>  $homeBySlot
     * @param  array<int, Attributes>  $awayBySlot
     * @return array{home: int, away: int}
     */
    public function resolve(array $homeBySlot, array $awayBySlot, int $seed): array
    {
        $home = $this->engine->simulate(
            Roster::fromAttributes($homeBySlot),
            $seed,
            Defense::fromAttributes($awayBySlot),
        )->goals;

        $away = $this->engine->simulate(
            Roster::fromAttributes($awayBySlot),
            $seed,
            Defense::fromAttributes($homeBySlot),
        )->goals;

        return ['home' => $home, 'away' => $away];
    }
}
