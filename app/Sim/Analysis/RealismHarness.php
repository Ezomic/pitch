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
        return MatchMetrics::sum($this->perSeed($matches, $rating));
    }

    /**
     * Each seed's own metrics, so a later run can be compared with this one seed
     * by seed.
     *
     * @return list<MatchMetrics>
     */
    public function perSeed(int $matches, int $rating = 72): array
    {
        $perSeed = [];

        for ($seed = 1; $seed <= $matches; $seed++) {
            $result = $this->engine->simulate($this->team($rating), $this->team($rating), $seed);
            $perSeed[] = $this->analyzer->analyze($result);
        }

        return $perSeed;
    }

    /**
     * @return array<int, Player>
     */
    private function team(int $rating): array
    {
        return Roster::build(new Attributes($rating, $rating, $rating, $rating, $rating, $rating));
    }
}
