<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

final readonly class ArmSummary
{
    public function __construct(
        public string $label,
        public int $vision,
        public int $matches,
        public float $meanDecisionGap,
        public float $progressivePassShare,
        public float $chancesPer90,
        public float $goalsPer90,
    ) {}
}
