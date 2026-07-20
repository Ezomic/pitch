<?php

declare(strict_types=1);

namespace App\Sim\Domain;

enum Position: string
{
    case Goalkeeper = 'GK';
    case Defender = 'DF';
    case Midfielder = 'MF';
    case Forward = 'FW';
}
