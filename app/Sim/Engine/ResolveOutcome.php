<?php

declare(strict_types=1);

namespace App\Sim\Engine;

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
    ) {}
}
