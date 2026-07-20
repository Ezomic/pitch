<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Zone;

final readonly class Option
{
    public function __construct(
        public EventType $type,
        public Zone $resultZone,
        public ?int $targetPlayerId,
        public float $threat,
    ) {}
}
