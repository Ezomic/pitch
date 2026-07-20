<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Zone;

final class MatchState
{
    public function __construct(
        public Zone $ballZone,
        public int $carrierId,
        public int $minute = 0,
    ) {}
}
