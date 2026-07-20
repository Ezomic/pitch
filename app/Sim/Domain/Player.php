<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Player
{
    public function __construct(
        public int $id,
        public string $name,
        public Position $position,
        public Zone $zone,
        public Attributes $attributes,
    ) {}

    public function withAttributes(Attributes $attributes): self
    {
        return new self($this->id, $this->name, $this->position, $this->zone, $attributes);
    }
}
