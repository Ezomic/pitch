<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Zone;
use App\Sim\Engine\MatchResult;

/**
 * Fold the two simulated legs into an ordered stream of ball-position frames for
 * a 2D replay. The home side attacks left to right; the opponent's leg is
 * mirrored so it attacks the other way. Derived straight from the deterministic
 * event log, so the same seed always yields the same replay.
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
            $x = $event->from->x / Zone::MAX_X;
            if ($mirror) {
                $x = 1 - $x;
            }

            $isShot = $event->type === EventType::Shot;
            $scored = $isShot && $event->success;

            $frames[] = [
                'm' => $event->minute,
                's' => $side,
                'x' => round($x, 3),
                'y' => round($event->from->y / Zone::MAX_Y, 3),
                't' => $event->type->value,
                'ok' => $event->success,
                'goal' => $scored,
                'start' => $startsPossession,
                'who' => $isShot ? ($side === 0 ? ($names[$event->actorId] ?? null) : 'Opposition') : null,
            ];

            $startsPossession = $isShot
                || (! $event->success && in_array($event->type, [EventType::Pass, EventType::Dribble], true));
        }

        return $frames;
    }
}
