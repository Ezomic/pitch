<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Defense;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

final class SquadEvaluator
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
    ) {}

    /**
     * Play the squad both ways against a fixed baseline opponent and average the
     * legibility metrics. The attack leg (user attacking the opponent's defence)
     * yields chances created; the defence leg (opponent attacking the user's
     * defence) yields chances conceded. Deterministic: the same squad always
     * yields the same profile, so a single swap produces a clean delta.
     *
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     */
    public function evaluate(array $bySlot, int $matches = 200): SquadProfile
    {
        $baseline = $this->baseline();

        $userAttackers = Roster::fromAttributes($bySlot);
        $opponentAttackers = Roster::fromAttributes($baseline);
        $userDefense = Defense::fromAttributes($bySlot);
        $opponentDefense = Defense::fromAttributes($baseline);

        $gapSum = 0.0;
        $decisions = 0;
        $progressive = 0;
        $passes = 0;
        $shots = 0;
        $goals = 0;
        $shotsConceded = 0;
        $goalsConceded = 0;

        for ($i = 0; $i < $matches; $i++) {
            $seed = $i + 1;

            $attack = $this->engine->simulate($userAttackers, $seed, $opponentDefense);
            $defence = $this->engine->simulate($opponentAttackers, $seed, $userDefense);

            $gapSum += $attack->decisionGapSum;
            $decisions += $attack->decisionCount;
            $progressive += $attack->progressivePasses;
            $passes += $attack->passesCompleted;
            $shots += $attack->shots;
            $goals += $attack->goals;

            $shotsConceded += $defence->shots;
            $goalsConceded += $defence->goals;
        }

        return new SquadProfile(
            meanDecisionGap: $decisions > 0 ? $gapSum / $decisions : 0.0,
            progressivePassShare: $passes > 0 ? $progressive / $passes : 0.0,
            chancesPer90: $matches > 0 ? $shots / $matches : 0.0,
            goalsPer90: $matches > 0 ? $goals / $matches : 0.0,
            chancesConcededPer90: $matches > 0 ? $shotsConceded / $matches : 0.0,
            goalsConcededPer90: $matches > 0 ? $goalsConceded / $matches : 0.0,
        );
    }

    /**
     * A fixed, average opponent: every slot rated 11 across the board.
     *
     * @return array<int, Attributes>
     */
    private function baseline(): array
    {
        $bySlot = [];
        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] = new Attributes(11, 11, 11, 11, 11, 11);
        }

        return $bySlot;
    }
}
