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

    // Defensive actions that stop an attack, crediting the defending side.
    case Interception = 'interception';
    case Tackle = 'tackle';
    case Clearance = 'clearance';
    case Save = 'save';
    case Block = 'block';

    // Wide play and dead balls.
    case Cross = 'cross';
    case Header = 'header';
    case Foul = 'foul';
    case Corner = 'corner';
    case ThrowIn = 'throw_in';
    case GoalKick = 'goal_kick';
    case Penalty = 'penalty';

    /** Whether this is a defending action, shown on the defending side. */
    public function isDefensive(): bool
    {
        return match ($this) {
            self::Interception, self::Tackle, self::Clearance, self::Save, self::Block => true,
            default => false,
        };
    }

    /** Whether this is an attempt on goal (open-play shot or a header). */
    public function isShot(): bool
    {
        return $this === self::Shot || $this === self::Header;
    }
}
