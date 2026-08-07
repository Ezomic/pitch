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
    private const int HALF_MINUTE = 45;

    public function __construct(
        private readonly MatchCommentary $commentary = new MatchCommentary,
    ) {}

    /**
     * @param  array<int, string>  $homeNames  slot id => player name
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @return list<array<string, mixed>>
     */
    public function build(MatchResult $home, ?MatchResult $away, array $homeNames, array $lineups = []): array
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

        return $this->collectNearest(
            $this->threadBall($this->withKickoffs($frames)),
            $lineups,
            $homeNames,
        );
    }

    /**
     * At a possession start the engine picks the carrier by formation band, not by
     * who is nearest the ball, so once the ball is threaded that player can be on
     * the far side sprinting across for a ball a team-mate was standing on.
     * Reassign each non-shot possession-start touch to the formation player nearest
     * to where the ball now is, so the closest man collects it and the caption,
     * dot and motion stay in step. Shots keep their real shooter.
     *
     * @param  list<array<string, mixed>>  $frames
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @param  array<int, string>  $homeNames
     * @return list<array<string, mixed>>
     */
    private function collectNearest(array $frames, array $lineups, array $homeNames): array
    {
        if ($lineups === []) {
            return $frames;
        }

        foreach ($frames as $i => $frame) {
            if ($frame['start'] !== true
                || in_array($frame['t'], ['kickoff', 'shot', 'header'], true)) {
                continue;
            }

            $side = (int) $frame['s'];
            $slot = $this->nearestAnchor($lineups, $side, (float) $frame['x1'], (float) $frame['y1']);

            if ($slot === null) {
                continue;
            }

            $actor = $this->name($slot, $side, $homeNames);
            $key = $this->commentary->keyFor((int) $frame['m'], $slot, $i);
            $type = EventType::from((string) $frame['t']);

            $frames[$i]['actorSlot'] = $slot;
            $frames[$i]['actor'] = $actor;
            $frames[$i]['label'] = $this->commentary->label(
                $type,
                (bool) $frame['ok'],
                (bool) $frame['goal'],
                $actor,
                is_string($frame['target']) ? $frame['target'] : null,
                $key,
            );
        }

        return $frames;
    }

    /**
     * The outfield slot on a side whose formation anchor is nearest a point.
     *
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     */
    private function nearestAnchor(array $lineups, int $side, float $x, float $y): ?int
    {
        $best = null;
        $bestDist = INF;

        foreach ($lineups as $line) {
            if ($line['s'] !== $side || $line['gk']) {
                continue;
            }

            $dist = ($line['x'] - $x) ** 2 + ($line['y'] - $y) ** 2;

            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $line['slot'];
            }
        }

        return $best;
    }

    /**
     * Thread the ball continuously through the replay: every frame begins where
     * the previous one ended, so the ball flows from touch to touch instead of
     * teleporting to each possession's formation start or snapping back to a
     * turnover logged at the pass origin. A stationary marker (a defensive win, a
     * dead ball) is moved onto where the ball actually is, and a kick-off keeps
     * the centre spot since the ball is genuinely reset there.
     *
     * @param  list<array<string, mixed>>  $frames
     * @return list<array<string, mixed>>
     */
    private function threadBall(array $frames): array
    {
        $prevX = null;
        $prevY = null;

        foreach ($frames as $i => $frame) {
            if ($frame['t'] === 'kickoff') {
                $prevX = $frame['x2'];
                $prevY = $frame['y2'];

                continue;
            }

            // A turnover or dead ball is stationary and moves onto the ball; a
            // shot only looks stationary when taken from the goal-mouth zone, so
            // it is excluded and keeps its flight to goal.
            $isShot = $frame['t'] === 'shot' || $frame['t'] === 'header';
            $stationary = ! $isShot
                && $frame['x1'] === $frame['x2']
                && $frame['y1'] === $frame['y2'];

            if ($prevX !== null) {
                $frames[$i]['x1'] = $prevX;
                $frames[$i]['y1'] = $prevY;

                if ($stationary) {
                    $frames[$i]['x2'] = $prevX;
                    $frames[$i]['y2'] = $prevY;
                }
            }

            $prevX = $frames[$i]['x2'];
            $prevY = $frames[$i]['y2'];
        }

        return $frames;
    }

    /**
     * Insert a kick-off from the centre spot where a real match has one: at the
     * opening whistle, at the start of the second half, and after every goal (the
     * conceding side restarts). Purely presentational, so the deterministic engine
     * never sees them.
     *
     * @param  list<array<string, mixed>>  $frames
     * @return list<array<string, mixed>>
     */
    private function withKickoffs(array $frames): array
    {
        if ($frames === []) {
            return [];
        }

        $out = [$this->kickoff(0, (int) $frames[0]['m'])];
        $secondHalf = false;

        foreach ($frames as $frame) {
            if (! $secondHalf && (int) $frame['m'] >= self::HALF_MINUTE) {
                $out[] = $this->kickoff(1, (int) $frame['m']);
                $secondHalf = true;
            }

            $out[] = $frame;

            if ($frame['goal'] === true) {
                $out[] = $this->kickoff($frame['s'] === 0 ? 1 : 0, (int) $frame['m']);
            }
        }

        return $out;
    }

    /**
     * A kick-off: the ball on the centre spot, tapped back into the restarting
     * side's own half. Home (0) attacks left to right, so it taps left; the
     * mirrored opponent taps right.
     *
     * @return array<string, mixed>
     */
    private function kickoff(int $side, int $minute): array
    {
        return [
            'm' => $minute,
            's' => $side,
            'x1' => 0.5,
            'y1' => 0.5,
            'x2' => $side === 0 ? 0.44 : 0.56,
            'y2' => 0.5,
            't' => 'kickoff',
            'ok' => true,
            'goal' => false,
            'start' => true,
            'actor' => null,
            'target' => null,
            'actorSlot' => null,
            'targetSlot' => null,
            'label' => 'Kick-off',
        ];
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
        // Defensive events are interleaved but do not carry the attacking flow, so
        // they are shown as a frame without touching this state.
        $startsPossession = true;

        foreach ($result->events as $index => $event) {
            $key = $this->commentary->key($event);

            if ($event->type->isDefensive()) {
                $frames[] = $this->defensiveFrame($event, $side, $mirror, $key);

                continue;
            }

            if (in_array($event->type, [EventType::Foul, EventType::Corner], true)) {
                $frames[] = $this->flavourFrame($event, $side, $mirror, $names, $key);

                continue;
            }

            $isShot = $event->type->isShot();
            $goal = $isShot && $event->success;

            [$x1, $y1] = $this->point($event->from->x / Zone::MAX_X, $event->from->y / Zone::MAX_Y, $mirror);
            [$x2, $y2] = $this->destination($event, $isShot, $mirror);

            $actor = $this->name($event->actorId, $side, $names);
            // The opponent has no named players, so a receiver name would just
            // read "Opposition → Opposition"; only name the home receiver.
            $target = (! $isShot && $side === 0) ? $this->name($event->targetId, 0, $names) : null;

            $frames[] = [
                'm' => $event->minute,
                's' => $side,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                't' => $event->type->value,
                'ok' => $event->success,
                'goal' => $goal,
                'start' => $startsPossession,
                'actor' => $actor,
                'target' => $target,
                // The engine's real actor and pass target (formation slots), so the
                // replay can put the exact players the engine chose on the ball
                // rather than whichever dot happens to be nearest.
                'actorSlot' => $event->actorId,
                'targetSlot' => $isShot ? null : $event->targetId,
                'label' => $this->commentary->label($event->type, $event->success, $goal, $actor, $target, $key),
            ];

            $startsPossession = $isShot
                || (! $event->success && in_array($event->type, [EventType::Pass, EventType::Dribble], true));
        }

        return $frames;
    }

    /**
     * A defensive event is the defending side winning the ball at a spot: shown
     * on the opposite side (flipped colour), a stationary marker with no pass.
     *
     * @return array<string, mixed>
     */
    private function defensiveFrame(MatchEvent $event, int $side, bool $mirror, int $key): array
    {
        [$x, $y] = $this->point($event->from->x / Zone::MAX_X, $event->from->y / Zone::MAX_Y, $mirror);

        return [
            'm' => $event->minute,
            's' => $side === 0 ? 1 : 0,
            'x1' => $x,
            'y1' => $y,
            'x2' => $x,
            'y2' => $y,
            't' => $event->type->value,
            'ok' => true,
            'goal' => false,
            'start' => false,
            'actor' => null,
            'target' => null,
            'actorSlot' => null,
            'targetSlot' => null,
            'label' => $this->commentary->label($event->type, true, false, null, null, $key),
        ];
    }

    /**
     * A dead ball the attack won (a foul or corner): a stationary marker on the
     * attacking side, named where we have the player.
     *
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function flavourFrame(MatchEvent $event, int $side, bool $mirror, array $names, int $key): array
    {
        [$x, $y] = $this->point($event->from->x / Zone::MAX_X, $event->from->y / Zone::MAX_Y, $mirror);
        $actor = $this->name($event->actorId, $side, $names);

        return [
            'm' => $event->minute,
            's' => $side,
            'x1' => $x,
            'y1' => $y,
            'x2' => $x,
            'y2' => $y,
            't' => $event->type->value,
            'ok' => true,
            'goal' => false,
            'start' => false,
            'actor' => $actor,
            'target' => null,
            'actorSlot' => $event->actorId,
            'targetSlot' => null,
            'label' => $this->commentary->label($event->type, true, false, $actor, null, $key),
        ];
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
