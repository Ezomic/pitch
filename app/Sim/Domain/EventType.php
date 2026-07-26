<?php

declare(strict_types=1);

namespace App\Sim\Domain;

enum EventType: string
{
    case Pass = 'pass';
    case Dribble = 'dribble';
    case Shot = 'shot';
    case Turnover = 'turnover';
    case Goal = 'goal';

    // Defensive actions that win the ball back, crediting the defending side.
    case Interception = 'interception';
    case Tackle = 'tackle';
    case Clearance = 'clearance';

    /** Whether this is a ball-winning defensive action rather than an attacking one. */
    public function isDefensive(): bool
    {
        return match ($this) {
            self::Interception, self::Tackle, self::Clearance => true,
            default => false,
        };
    }
}
