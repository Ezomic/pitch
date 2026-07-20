<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;

final readonly class MatchResult
{
    public int $passesCompleted;

    public int $progressivePasses;

    public int $shots;

    public int $goals;

    public float $decisionGapSum;

    public int $decisionCount;

    /**
     * @param  list<MatchEvent>  $events
     */
    public function __construct(public array $events)
    {
        $passesCompleted = 0;
        $progressivePasses = 0;
        $shots = 0;
        $goals = 0;
        $gapSum = 0.0;
        $decisions = 0;

        foreach ($events as $event) {
            if ($event->decision !== null) {
                $gapSum += $event->decision->gap();
                $decisions++;
            }

            if ($event->type === EventType::Pass && $event->success) {
                $passesCompleted++;
                if ($event->isProgressivePass()) {
                    $progressivePasses++;
                }
            }

            if ($event->type === EventType::Shot) {
                $shots++;
                if ($event->success) {
                    $goals++;
                }
            }
        }

        $this->passesCompleted = $passesCompleted;
        $this->progressivePasses = $progressivePasses;
        $this->shots = $shots;
        $this->goals = $goals;
        $this->decisionGapSum = $gapSum;
        $this->decisionCount = $decisions;
    }
}
