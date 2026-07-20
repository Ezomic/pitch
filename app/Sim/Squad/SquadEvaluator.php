<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

final class SquadEvaluator
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
    ) {}

    /**
     * Run a squad through a fixed sequence of seeded matches and average the
     * legibility metrics. Deterministic: the same squad always yields the same
     * profile, so a single player swap produces a clean, honest delta.
     *
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     */
    public function evaluate(array $bySlot, int $matches = 200): SquadProfile
    {
        $players = Roster::fromAttributes($bySlot);

        $gapSum = 0.0;
        $decisions = 0;
        $progressive = 0;
        $passes = 0;
        $shots = 0;
        $goals = 0;

        for ($i = 0; $i < $matches; $i++) {
            $result = $this->engine->simulate($players, $i + 1);

            $gapSum += $result->decisionGapSum;
            $decisions += $result->decisionCount;
            $progressive += $result->progressivePasses;
            $passes += $result->passesCompleted;
            $shots += $result->shots;
            $goals += $result->goals;
        }

        return new SquadProfile(
            meanDecisionGap: $decisions > 0 ? $gapSum / $decisions : 0.0,
            progressivePassShare: $passes > 0 ? $progressive / $passes : 0.0,
            chancesPer90: $matches > 0 ? $shots / $matches : 0.0,
            goalsPer90: $matches > 0 ? $goals / $matches : 0.0,
        );
    }
}
