<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Roll
{
    public function __construct(
        public float $baseDifficulty,
        public float $attributeContribution,
        public float $pressure,
        public float $threshold,
        public float $draw,
    ) {}

    public function succeeded(): bool
    {
        return $this->draw <= $this->threshold;
    }
}
