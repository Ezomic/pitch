<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Zone;
use App\Sim\Pitch\PitchResult;

/**
 * Turn a simulated match into football metrics: the counts and territory a real
 * match report would carry, so engine behaviour can be measured against real-world
 * ranges instead of judged by eye.
 */
final class MatchAnalyzer
{
    public function analyze(PitchResult $result): MatchMetrics
    {
        $shots = 0;
        $shotsOnGoal = 0;
        $saves = 0;
        $passes = 0;
        $passesCompleted = 0;
        $crosses = 0;
        $crossesCompleted = 0;
        $fouls = 0;
        $corners = 0;
        $throwIns = 0;
        $goalKicks = 0;
        $penalties = 0;
        $defensive = 0;
        $shotAdvanceSum = 0.0;

        foreach ($result->events as $event) {
            if ($event->type->isShot()) {
                $shots++;
                $shotAdvanceSum += $event->from->x / Zone::MAX_X;

                if ($event->success) {
                    $shotsOnGoal++;
                }

                continue;
            }

            match ($event->type) {
                EventType::Pass => [$passes++, $event->success ? $passesCompleted++ : null],
                EventType::Cross => [$crosses++, $event->success ? $crossesCompleted++ : null],
                EventType::Save => $saves++,
                EventType::Foul => $fouls++,
                EventType::Corner => $corners++,
                EventType::ThrowIn => $throwIns++,
                EventType::GoalKick => $goalKicks++,
                EventType::Penalty => $penalties++,
                EventType::Tackle, EventType::SlideTackle, EventType::Interception, EventType::Clearance => $defensive++,
                default => null,
            };
        }

        [$frames, $framesHome, $framesFinal, $framesMiddle] = $this->territory($result->frames);

        return new MatchMetrics(
            matches: 1,
            goals: $result->homeGoals + $result->awayGoals,
            shots: $shots,
            // On target = scored plus saved; a blocked or off-target effort is not.
            shotsOnTarget: $shotsOnGoal + $saves,
            passes: $passes,
            passesCompleted: $passesCompleted,
            crosses: $crosses,
            crossesCompleted: $crossesCompleted,
            fouls: $fouls,
            corners: $corners,
            throwIns: $throwIns,
            goalKicks: $goalKicks,
            penalties: $penalties,
            defensiveActions: $defensive,
            frames: $frames,
            framesHome: $framesHome,
            framesFinalThird: $framesFinal,
            framesMiddleThird: $framesMiddle,
            shotAdvanceSum: $shotAdvanceSum,
        );
    }

    /**
     * @param  list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>  $frames
     * @return array{int, int, int, int} total, home-possession, final-third, middle-third
     */
    private function territory(array $frames): array
    {
        $total = 0;
        $home = 0;
        $final = 0;
        $middle = 0;

        foreach ($frames as $frame) {
            $total++;

            if ($frame['s'] === 0) {
                $home++;
            }

            // How far the possessing side has the ball up its own attacking axis.
            $advance = $frame['s'] === 0 ? $frame['b'][0] : 1.0 - $frame['b'][0];

            if ($advance > 0.66) {
                $final++;
            } elseif ($advance >= 0.34) {
                $middle++;
            }
        }

        return [$total, $home, $final, $middle];
    }
}
