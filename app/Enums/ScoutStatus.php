<?php

declare(strict_types=1);

namespace App\Enums;

enum ScoutStatus: string
{
    case Available = 'available';
    case Idle = 'idle';
    case Scouting = 'scouting';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available to hire',
            self::Idle => 'On the books',
            self::Scouting => 'Out scouting',
        };
    }
}
