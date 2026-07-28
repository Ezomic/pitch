<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * One side's output over a batch (matches = 1 per match, summed for a batch).
 * Kept to the figures that move cleanly with a single attribute, so an experiment
 * can read the effect of changing one thing.
 */
final readonly class SideMetrics
{
    public function __construct(
        public int $matches,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $shots,
        public int $passes,
        public int $passesCompleted,
        public int $frames,
        public int $framesInPossession,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0, 0);
    }

    public function add(self $o): self
    {
        return new self(
            $this->matches + $o->matches,
            $this->goalsFor + $o->goalsFor,
            $this->goalsAgainst + $o->goalsAgainst,
            $this->shots + $o->shots,
            $this->passes + $o->passes,
            $this->passesCompleted + $o->passesCompleted,
            $this->frames + $o->frames,
            $this->framesInPossession + $o->framesInPossession,
        );
    }
}
