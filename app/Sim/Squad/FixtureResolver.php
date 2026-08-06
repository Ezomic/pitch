<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Rng;
use App\Sim\Engine\SetPieces;

final class FixtureResolver
{
    /**
     * How much playing at home is worth, as a multiplier on each side's attack.
     * Sized against the real top-division split (roughly 45% home wins to 29%
     * away): it is the gap between the two that matters, not either number.
     */
    private const float HOME_ATTACK = 1.06;

    private const float AWAY_ATTACK = 0.96;

    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly SetPieces $setPieces = new SetPieces,
    ) {}

    /**
     * Resolve a single fixture between two teams: each side attacks the other's
     * defence, using its own formation and mentality, with a set-piece phase
     * added on top and the home side's advantage applied. Deterministic.
     *
     * @return array{home: int, away: int}
     */
    public function resolve(TeamSetup $home, TeamSetup $away, int $seed): array
    {
        $awaySeed = $this->awaySeed($seed);

        $homeOpen = $this->engine->simulate(
            $home->attackers(),
            $seed,
            $away->defence(),
            $home->formation,
            $home->attackBias() * self::HOME_ATTACK,
        );

        $awayOpen = $this->engine->simulate(
            $away->attackers(),
            $awaySeed,
            $home->defence(),
            $away->formation,
            $away->attackBias() * self::AWAY_ATTACK,
        );

        $homeSet = $this->setPieces->resolve($home->setPiece, $away->defence(), $seed, $homeOpen->shots);
        $awaySet = $this->setPieces->resolve($away->setPiece, $home->defence(), $awaySeed + 1, $awayOpen->shots);

        return [
            'home' => $homeOpen->goals + $homeSet['goals'],
            'away' => $awayOpen->goals + $awaySet['goals'],
        ];
    }

    /**
     * A separate point in the stream for the away side. Both attacks used to run
     * from the same seed, which correlated them hard: two identical setups drew
     * on 93% of seeds, because the same numbers were driving both ends of the
     * pitch. Taking the next value from the seed's own stream keeps the fixture
     * reproducible while making the two sides independent.
     */
    private function awaySeed(int $seed): int
    {
        return (new Rng($seed))->nextInt();
    }
}
