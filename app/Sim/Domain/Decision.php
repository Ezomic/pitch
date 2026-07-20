<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Decision
{
    public function __construct(
        public int $optionsVisible,
        public int $optionsTotal,
        public float $chosenThreat,
        public float $bestAvailableThreat,
    ) {}

    public function gap(): float
    {
        return $this->bestAvailableThreat - $this->chosenThreat;
    }
}
