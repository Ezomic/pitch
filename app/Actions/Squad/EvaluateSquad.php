<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\SquadProfile;
use App\Sim\Squad\TeamSetup;

class EvaluateSquad
{
    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    public function handle(Squad $squad, int $matches = 200): SquadProfile
    {
        return $this->evaluator->evaluate($squad->setup(), TeamSetup::baseline(), $matches);
    }
}
