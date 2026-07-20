<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

final readonly class RunReport
{
    private const float GAP_MARGIN = 0.02;

    private const float PROG_MARGIN = 0.02;

    private const float CHANCES_MARGIN = 0.3;

    /**
     * @param  list<SampledMatch>  $samples
     */
    public function __construct(
        public int $runSeed,
        public int $matches,
        public ArmSummary $low,
        public ArmSummary $high,
        public array $samples = [],
    ) {}

    public function gapImprovement(): float
    {
        return $this->low->meanDecisionGap - $this->high->meanDecisionGap;
    }

    public function progressiveLift(): float
    {
        return $this->high->progressivePassShare - $this->low->progressivePassShare;
    }

    public function chancesLift(): float
    {
        return $this->high->chancesPer90 - $this->low->chancesPer90;
    }

    public function separated(): bool
    {
        return $this->gapImprovement() > self::GAP_MARGIN
            && $this->progressiveLift() > self::PROG_MARGIN
            && $this->chancesLift() > self::CHANCES_MARGIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'run_seed' => $this->runSeed,
            'matches' => $this->matches,
            'low_vision' => $this->low->vision,
            'high_vision' => $this->high->vision,
        ];
    }
}
