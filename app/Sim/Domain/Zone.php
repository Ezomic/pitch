<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Zone
{
    public const int MAX_X = 5;

    public const int MAX_Y = 4;

    public const int CENTRE_Y = 2;

    public function __construct(
        public int $x,
        public int $y,
    ) {}

    public function threat(): float
    {
        $advance = ($this->x / self::MAX_X) * 0.85;
        $central = $this->y === self::CENTRE_Y ? 0.15 : 0.0;

        return $advance + $central;
    }

    public function inShootingRange(): bool
    {
        return $this->x >= 4;
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }
}
