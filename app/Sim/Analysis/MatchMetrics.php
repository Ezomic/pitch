<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * Raw counts for one match (matches = 1) or a summed batch (matches = N). Kept as
 * plain totals so batches aggregate by simple addition; the per-match rates and
 * ratios are derived in RealismReport, never stored here.
 */
final readonly class MatchMetrics
{
    public function __construct(
        public int $matches,
        public int $goals,
        public int $shots,
        public int $shotsOnTarget,
        public int $passes,
        public int $passesCompleted,
        public int $crosses,
        public int $crossesCompleted,
        public int $fouls,
        public int $corners,
        public int $throwIns,
        public int $goalKicks,
        public int $penalties,
        public int $defensiveActions,
        public int $frames,
        public int $framesHome,
        public int $framesFinalThird,
        public int $framesMiddleThird,
        public float $shotAdvanceSum,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0);
    }

    public function add(self $o): self
    {
        return new self(
            $this->matches + $o->matches,
            $this->goals + $o->goals,
            $this->shots + $o->shots,
            $this->shotsOnTarget + $o->shotsOnTarget,
            $this->passes + $o->passes,
            $this->passesCompleted + $o->passesCompleted,
            $this->crosses + $o->crosses,
            $this->crossesCompleted + $o->crossesCompleted,
            $this->fouls + $o->fouls,
            $this->corners + $o->corners,
            $this->throwIns + $o->throwIns,
            $this->goalKicks + $o->goalKicks,
            $this->penalties + $o->penalties,
            $this->defensiveActions + $o->defensiveActions,
            $this->frames + $o->frames,
            $this->framesHome + $o->framesHome,
            $this->framesFinalThird + $o->framesFinalThird,
            $this->framesMiddleThird + $o->framesMiddleThird,
            $this->shotAdvanceSum + $o->shotAdvanceSum,
        );
    }
}
