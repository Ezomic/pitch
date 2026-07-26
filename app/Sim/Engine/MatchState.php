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
        // Set the tick after a cross connects, so the next attempt is a header.
        public bool $headerNext = false,
    ) {}
}
