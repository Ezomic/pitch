<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

use App\Sim\Engine\MatchResult;

final class ArmTally
{
    private int $matches = 0;

    private float $gapSum = 0.0;

    private int $decisions = 0;

    private int $progressivePasses = 0;

    private int $passesCompleted = 0;

    private int $shots = 0;

    private int $goals = 0;

    public function __construct(
        private readonly string $label,
        private readonly int $vision,
    ) {}

    public function add(MatchResult $result): void
    {
        $this->matches++;
        $this->gapSum += $result->decisionGapSum;
        $this->decisions += $result->decisionCount;
        $this->progressivePasses += $result->progressivePasses;
        $this->passesCompleted += $result->passesCompleted;
        $this->shots += $result->shots;
        $this->goals += $result->goals;
    }

    public function summary(): ArmSummary
    {
        return new ArmSummary(
            $this->label,
            $this->vision,
            $this->matches,
            $this->decisions > 0 ? $this->gapSum / $this->decisions : 0.0,
            $this->passesCompleted > 0 ? $this->progressivePasses / $this->passesCompleted : 0.0,
            $this->matches > 0 ? $this->shots / $this->matches : 0.0,
            $this->matches > 0 ? $this->goals / $this->matches : 0.0,
        );
    }
}
