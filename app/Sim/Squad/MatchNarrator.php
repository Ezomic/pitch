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
     */
    public function narrate(MatchResult $attack, int $opponentGoals, array $names, ?MatchResult $defence = null): MatchReport
    {
        return new MatchReport(
            homeGoals: $attack->goals,
            awayGoals: $opponentGoals,
            shots: $attack->shots,
            passesCompleted: $attack->passesCompleted,
            progressivePasses: $attack->progressivePasses,
            moments: $this->feed($attack, $names),
            timeline: $defence !== null ? $this->timeline->build($attack, $defence, $names) : [],
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

        if ($event->type === EventType::Foul) {
            return new MatchMoment($event->minute, 'setpiece', 'A foul concedes a free-kick in a dangerous area.');
        }

        if ($event->type === EventType::Corner) {
            return new MatchMoment($event->minute, 'setpiece', 'The pressure earns a corner.');
        }

        $actor = $names[$event->actorId] ?? "Player {$event->actorId}";
        $target = $event->targetId !== null ? ($names[$event->targetId] ?? "Player {$event->targetId}") : null;

        if ($event->type === EventType::Header) {
            return $event->success
                ? new MatchMoment($event->minute, 'goal', "GOAL! {$actor} heads it home.")
                : new MatchMoment($event->minute, 'shot', "{$actor} gets on the end of the cross but cannot score.");
        }

        if ($event->type === EventType::Shot) {
            return $event->success
                ? new MatchMoment($event->minute, 'goal', "GOAL! {$actor} finds the net.")
                : new MatchMoment($event->minute, 'shot', "{$actor} shoots, but it is saved.");
        }

        if ($event->type === EventType::Cross && $event->success) {
            return new MatchMoment($event->minute, 'chance', "{$actor} whips a cross towards {$target}.");
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
            EventType::Save => 'The keeper gets down to save it.',
            EventType::Block => 'A defender throws himself in front of the shot.',
            default => 'The pass is cut out.',
        };

        $kind = in_array($event->type, [EventType::Save, EventType::Block], true) ? 'save' : 'turnover';

        return new MatchMoment($event->minute, $kind, $text);
    }
}
