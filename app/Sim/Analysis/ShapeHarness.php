<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Player;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;

/**
 * Average every player's position across a batch of matches. The frame stream
 * already logs all 22 positions each tick, so this just accumulates them: the
 * result is the mean shape each side settles into, the raw material for line
 * height, compactness and a heatmap. Deterministic over seeds 1..N.
 */
final class ShapeHarness
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
    ) {}

    public function run(int $matches, int $rating = 72): ShapeResult
    {
        // Index 0..10 = home (0 = keeper), 11..21 = away, matching the frame order.
        $sumX = array_fill(0, 22, 0.0);
        $sumY = array_fill(0, 22, 0.0);
        $frames = 0;

        for ($seed = 1; $seed <= $matches; $seed++) {
            $result = $this->engine->simulate($this->team($rating), $this->team($rating), $seed);

            foreach ($result->frames as $frame) {
                foreach ($frame['p'] as $i => [$x, $y]) {
                    $sumX[$i] += $x;
                    $sumY[$i] += $y;
                }
                $frames++;
            }
        }

        $divisor = max(1, $frames);
        $positions = [];
        for ($i = 0; $i < 22; $i++) {
            $positions[$i] = [$sumX[$i] / $divisor, $sumY[$i] / $divisor];
        }

        return new ShapeResult($positions, $matches);
    }

    /**
     * @return array<int, Player>
     */
    private function team(int $rating): array
    {
        return Roster::build(new Attributes($rating, $rating, $rating, $rating, $rating, $rating));
    }
}
