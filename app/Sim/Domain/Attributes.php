<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Attributes
{
    public function __construct(
        public int $vision,
        public int $passing,
        public int $dribbling,
        public int $finishing,
        public int $tackling,
        public int $pace,
    ) {}

    public function withVision(int $vision): self
    {
        return new self($vision, $this->passing, $this->dribbling, $this->finishing, $this->tackling, $this->pace);
    }
}
