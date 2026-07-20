<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

use App\Sim\Engine\MatchResult;

final readonly class SampledMatch
{
    public function __construct(
        public string $arm,
        public int $seed,
        public MatchResult $result,
    ) {}
}
