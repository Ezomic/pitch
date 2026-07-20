<?php

declare(strict_types=1);

namespace App\Sim\Engine;

enum Mentality: string
{
    case Attacking = 'attacking';
    case Balanced = 'balanced';
    case Defensive = 'defensive';

    public function label(): string
    {
        return match ($this) {
            self::Attacking => 'Attacking',
            self::Balanced => 'Balanced',
            self::Defensive => 'Defensive',
        };
    }

    /**
     * Multiplier on the attacking side's success. Above 1 commits forward.
     */
    public function attackBias(): float
    {
        return match ($this) {
            self::Attacking => 1.10,
            self::Balanced => 1.0,
            self::Defensive => 0.90,
        };
    }

    /**
     * Multiplier on the defending side's resistance. Above 1 sits deeper.
     */
    public function defenceBias(): float
    {
        return match ($this) {
            self::Attacking => 0.85,
            self::Balanced => 1.0,
            self::Defensive => 1.15,
        };
    }

    public static function fromId(?string $id): self
    {
        return self::tryFrom($id ?? '') ?? self::Balanced;
    }

    public static function default(): self
    {
        return self::Balanced;
    }
}
