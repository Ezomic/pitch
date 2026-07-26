<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Roll;
use App\Sim\Domain\Zone;

final readonly class ResolveOutcome
{
    public function __construct(
        public Roll $roll,
        public bool $success,
        public bool $possessionEnds,
        public ?Zone $newBallZone,
        public ?int $newCarrierId,
        public bool $chanceCreated,
        public bool $goal,
        // The defending side's stop that ended the possession (tackle, save, ...).
        public ?EventType $turnover = null,
        // A dead ball the attack won at the same moment (a foul or a corner).
        public ?EventType $deadBall = null,
        // Set by a connecting cross so the next attempt is resolved as a header.
        public bool $setsHeader = false,
    ) {}
}
