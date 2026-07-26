<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\MatchResult;

final class MatchNarrator
{
    public function __construct(
        private readonly MatchTimeline $timeline = new MatchTimeline,
    ) {}

    /**
     * Turn one attacking match into a readable report: a scoreline plus a curated,
     * minute-ordered highlights feed rather than every pass. When the opponent's
     * leg is supplied, a 2D-replay timeline is folded in from both legs.
     *
     * @param  array<int, string>  $names  slot id => player name
     * @param  list<array<string, mixed>>  $lineups  both teams' formation positions
     */
    public function narrate(MatchResult $attack, int $opponentGoals, array $names, ?MatchResult $defence = null, array $lineups = []): MatchReport
    {
        return new MatchReport(
            homeGoals: $attack->goals,
            awayGoals: $opponentGoals,
            shots: $attack->shots,
            passesCompleted: $attack->passesCompleted,
            progressivePasses: $attack->progressivePasses,
            moments: $this->feed($attack, $names),
            timeline: $defence !== null ? $this->timeline->build($attack, $defence, $names) : [],
            lineups: $defence !== null ? $lineups : [],
        );
    }

    /**
     * The curated highlights of one attacking side, in minute order.
     *
     * @param  array<int, string>  $names  slot id => player name
     * @return list<MatchMoment>
     */
    public function feed(MatchResult $attack, array $names): array
    {
        $moments = [];

        foreach ($attack->events as $event) {
            $moment = $this->moment($event, $names);

            if ($moment !== null) {
                $moments[] = $moment;
            }
        }

        return $moments;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function moment(MatchEvent $event, array $names): ?MatchMoment
    {
        if ($event->type->isDefensive()) {
            return $this->defensiveMoment($event);
        }

        $actor = $names[$event->actorId] ?? "Player {$event->actorId}";
        $target = $event->targetId !== null ? ($names[$event->targetId] ?? "Player {$event->targetId}") : null;

        if ($event->type === EventType::Shot) {
            return $event->success
                ? new MatchMoment($event->minute, 'goal', "GOAL! {$actor} finds the net.")
                : new MatchMoment($event->minute, 'shot', "{$actor} shoots, but it is saved.");
        }

        if ($event->isProgressivePass() && $event->to !== null && $event->to->x >= 4) {
            $text = $event->to->x >= 5
                ? "{$actor} slips {$target} in behind."
                : "{$actor} works it into the final third to {$target}.";

            return new MatchMoment($event->minute, 'chance', $text);
        }

        return null;
    }

    /**
     * A ball-winning defensive action, surfaced only when it snuffs out a threat
     * in a dangerous area so the feed stays curated rather than listing every
     * routine interception.
     */
    private function defensiveMoment(MatchEvent $event): ?MatchMoment
    {
        if ($event->from->x < 3) {
            return null;
        }

        $text = match ($event->type) {
            EventType::Tackle => 'A crunching tackle wins it back.',
            EventType::Clearance => 'The defence hacks the danger clear.',
            default => 'The pass is cut out.',
        };

        return new MatchMoment($event->minute, 'turnover', $text);
    }
}
