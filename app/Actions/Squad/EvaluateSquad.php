<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\SquadProfile;

class EvaluateSquad
{
    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    public function handle(Squad $squad, int $matches = 200): SquadProfile
    {
        return $this->evaluator->evaluate($squad->attributesBySlot(), $matches);
    }
}
