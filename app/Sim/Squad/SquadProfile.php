<?php

declare(strict_types=1);

namespace App\Sim\Squad;

final readonly class SquadProfile
{
    public function __construct(
        public float $meanDecisionGap,
        public float $progressivePassShare,
        public float $chancesPer90,
        public float $goalsPer90,
        public float $chancesConcededPer90,
        public float $goalsConcededPer90,
    ) {}

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'mean_decision_gap' => $this->meanDecisionGap,
            'progressive_pass_share' => $this->progressivePassShare,
            'chances_per_90' => $this->chancesPer90,
            'goals_per_90' => $this->goalsPer90,
            'chances_conceded_per_90' => $this->chancesConcededPer90,
            'goals_conceded_per_90' => $this->goalsConcededPer90,
        ];
    }
}
