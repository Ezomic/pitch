<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\SquadProfile;
use App\Sim\Squad\TeamSetup;

/**
 * Evaluate the same players under two different shapes at once, so a formation or
 * mentality change can be read as a clean side-by-side diff of the team profile.
 */
class CompareSetups
{
    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    /**
     * @return array{a: array<string, float>, b: array<string, float>}
     */
    public function handle(Squad $squad, string $formationA, string $mentalityA, string $formationB, string $mentalityB): array
    {
        $bySlot = $squad->attributesBySlot();
        $opponent = TeamSetup::baseline();

        return [
            'a' => $this->profile($this->evaluate($bySlot, $formationA, $mentalityA, $opponent)),
            'b' => $this->profile($this->evaluate($bySlot, $formationB, $mentalityB, $opponent)),
        ];
    }

    /**
     * @param  array<int, Attributes>  $bySlot
     */
    private function evaluate(array $bySlot, string $formation, string $mentality, TeamSetup $opponent): SquadProfile
    {
        $setup = new TeamSetup($bySlot, Formation::fromId($formation), Mentality::fromId($mentality));

        return $this->evaluator->evaluate($setup, $opponent);
    }

    /**
     * @return array<string, float>
     */
    private function profile(SquadProfile $profile): array
    {
        return [
            'meanDecisionGap' => $profile->meanDecisionGap,
            'progressivePassShare' => $profile->progressivePassShare,
            'chancesPer90' => $profile->chancesPer90,
            'goalsPer90' => $profile->goalsPer90,
            'chancesConcededPer90' => $profile->chancesConcededPer90,
            'goalsConcededPer90' => $profile->goalsConcededPer90,
        ];
    }
}
