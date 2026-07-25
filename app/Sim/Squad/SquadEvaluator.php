<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\SetPieces;

final class SquadEvaluator
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly SetPieces $setPieces = new SetPieces,
    ) {}

    /**
     * Play the user's setup both ways against an opponent setup and average the
     * legibility metrics. The attack leg (user attacking the opponent's defence)
     * yields chances created; the defence leg (opponent attacking the user's
     * defence) yields chances conceded. Deterministic: the same setups always
     * yield the same profile, so a single swap or tactical change produces a
     * clean delta.
     */
    public function evaluate(TeamSetup $user, TeamSetup $opponent, int $matches = 200): SquadProfile
    {
        $userAttackers = $user->attackers();
        $opponentAttackers = $opponent->attackers();
        $userDefence = $user->defence();
        $opponentDefence = $opponent->defence();

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

            $attack = $this->engine->simulate($userAttackers, $seed, $opponentDefence, $user->formation, $user->attackBias());
            $defence = $this->engine->simulate($opponentAttackers, $seed, $userDefence, $opponent->formation, $opponent->attackBias());

            $attackSet = $this->setPieces->resolve($user->setPiece, $opponentDefence, $seed, $attack->shots);
            $defenceSet = $this->setPieces->resolve($opponent->setPiece, $userDefence, $seed + 1, $defence->shots);

            $gapSum += $attack->decisionGapSum;
            $decisions += $attack->decisionCount;
            $progressive += $attack->progressivePasses;
            $passes += $attack->passesCompleted;
            $shots += $attack->shots + $attackSet['chances'];
            $goals += $attack->goals + $attackSet['goals'];

            $shotsConceded += $defence->shots + $defenceSet['chances'];
            $goalsConceded += $defence->goals + $defenceSet['goals'];
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
}
