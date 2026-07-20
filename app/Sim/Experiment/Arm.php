<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

final readonly class Arm
{
    public function __construct(
        public string $label,
        public int $vision,
    ) {}
}
