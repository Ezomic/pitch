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
}
