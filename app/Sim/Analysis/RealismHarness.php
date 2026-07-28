<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Player;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;

/**
 * Simulate a batch of matches between two evenly-rated sides and sum their
 * metrics. Deterministic: seeds 1..N, so the same batch always yields the same
 * totals and the report is stable and comparable across runs.
 */
final class RealismHarness
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly MatchAnalyzer $analyzer = new MatchAnalyzer,
    ) {}

    public function run(int $matches, int $rating = 72): MatchMetrics
    {
        $total = MatchMetrics::zero();

        for ($seed = 1; $seed <= $matches; $seed++) {
            $result = $this->engine->simulate($this->team($rating), $this->team($rating), $seed);
            $total = $total->add($this->analyzer->analyze($result));
        }

        return $total;
    }

    /**
     * @return array<int, Player>
     */
    private function team(int $rating): array
    {
        return Roster::build(new Attributes($rating, $rating, $rating, $rating, $rating, $rating));
    }
}
