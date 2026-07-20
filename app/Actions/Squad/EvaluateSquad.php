<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\SquadProfile;

class EvaluateSquad
{
    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    public function handle(Squad $squad, int $matches = 200): SquadProfile
    {
        $bySlot = [];

        foreach ($squad->assignments as $assignment) {
            $bySlot[$assignment->slot] = $assignment->player->attributes();
        }

        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] ??= new Attributes(10, 10, 10, 10, 10, 10);
        }

        return $this->evaluator->evaluate($bySlot, $matches);
    }
}
