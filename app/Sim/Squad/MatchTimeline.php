<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\MatchResult;

/**
 * Fold the two simulated legs into an ordered stream of ball-position frames for
 * a 2D replay. Each frame carries where the ball starts (the player on it) and
 * where it goes (the receiver or the goal), plus both players' names, so the
 * replay can show who passed to whom. The home side attacks left to right; the
 * opponent's leg is mirrored so it attacks the other way. Derived straight from
 * the deterministic event log, so the same seed always yields the same replay.
 */
final class MatchTimeline
{
    /**
     * @param  array<int, string>  $homeNames  slot id => player name
     * @return list<array<string, mixed>>
     */
    public function build(MatchResult $home, ?MatchResult $away, array $homeNames): array
    {
        $frames = $this->leg($home, side: 0, mirror: false, names: $homeNames);

        if ($away !== null) {
            foreach ($this->leg($away, side: 1, mirror: true, names: []) as $frame) {
                $frames[] = $frame;
            }
        }

        // Stable sort by minute (PHP 8 sort is stable), so each possession stays
        // intact and home ties fall before away ties, giving a coherent to-and-fro.
        usort($frames, fn (array $a, array $b): int => $a['m'] <=> $b['m']);

        return $frames;
    }

    /**
     * @param  array<int, string>  $names
     * @return list<array<string, mixed>>
     */
    private function leg(MatchResult $result, int $side, bool $mirror, array $names): array
    {
        $frames = [];
        // The first event of the leg opens a possession; thereafter a shot or a
        // lost pass/dribble ends one, so the next event starts a fresh possession.
        $startsPossession = true;

        foreach ($result->events as $event) {
            $isShot = $event->type === EventType::Shot;

            [$x1, $y1] = $this->point($event->from->x / Zone::MAX_X, $event->from->y / Zone::MAX_Y, $mirror);
            [$x2, $y2] = $this->destination($event, $isShot, $mirror);

            $frames[] = [
                'm' => $event->minute,
                's' => $side,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                't' => $event->type->value,
                'ok' => $event->success,
                'goal' => $isShot && $event->success,
                'start' => $startsPossession,
                'actor' => $this->name($event->actorId, $side, $names),
                // The opponent has no named players, so a receiver name would just
                // read "Opposition → Opposition"; only name the home receiver.
                'target' => (! $isShot && $side === 0) ? $this->name($event->targetId, 0, $names) : null,
            ];

            $startsPossession = $isShot
                || (! $event->success && in_array($event->type, [EventType::Pass, EventType::Dribble], true));
        }

        return $frames;
    }

    /**
     * Where the ball travels: the event's own target zone, or the attacking goal
     * for a shot, or the origin when nothing else is known.
     *
     * @return array{float, float}
     */
    private function destination(MatchEvent $event, bool $isShot, bool $mirror): array
    {
        if ($event->to !== null) {
            return $this->point($event->to->x / Zone::MAX_X, $event->to->y / Zone::MAX_Y, $mirror);
        }

        if ($isShot) {
            return $this->point(1.0, 0.5, $mirror);
        }

        return $this->point($event->from->x / Zone::MAX_X, $event->from->y / Zone::MAX_Y, $mirror);
    }

    /**
     * @return array{float, float}
     */
    private function point(float $x, float $y, bool $mirror): array
    {
        if ($mirror) {
            $x = 1 - $x;
        }

        return [round($x, 3), round($y, 3)];
    }

    /**
     * @param  array<int, string>  $names
     */
    private function name(?int $slot, int $side, array $names): ?string
    {
        if ($slot === null) {
            return null;
        }

        if ($side === 1) {
            return 'Opposition';
        }

        return $names[$slot] ?? "Player {$slot}";
    }
}
