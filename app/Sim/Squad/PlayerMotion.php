<?php

declare(strict_types=1);

namespace App\Sim\Squad;

/**
 * A deterministic motion layer for the 2D replay, DERIVED from the ball-position
 * timeline rather than simulated. The zone engine only ever tracks the ball, so
 * this nudges each of the 22 players around their formation anchor to suggest a
 * living shape: the team in possession pushes up and shifts ball-side, the team
 * out of possession drops toward its own goal and the nearest defender presses,
 * and the player closest to the ball carries it. Same timeline in, same motion
 * out, so the replay stays reproducible.
 *
 * Positions are emitted per timeline frame in a compact shape: `b` is the index
 * of the ball carrier and `p` holds an [x, y] pair for each player, both in the
 * same order as the lineups, so the frontend pairs identity (lineups) with
 * movement (positions) by index without shipping the identity fields again.
 */
final class PlayerMotion
{
    private const float FOLLOW_ATTACK = 0.34;

    private const float FOLLOW_DEFEND = 0.24;

    private const float BALL_SIDE = 0.22;

    private const float PRESS = 0.6;

    /** How much the team in possession fans out wide. */
    private const float SPREAD = 0.12;

    /** How much the team out of possession squeezes toward the middle. */
    private const float COMPACT = 0.18;

    /** How hard a forward runs in behind as the ball advances. */
    private const float RUN = 0.4;

    /** How strongly a defender tucks toward the nearest attacker to mark. */
    private const float MARK = 0.25;

    private const float MIN = 0.03;

    private const float MAX = 0.97;

    /**
     * @param  list<array<string, mixed>>  $timeline  ball-position frames
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @return list<array{b: int, p: list<array{float, float}>}>
     */
    public function build(array $timeline, array $lineups): array
    {
        $frames = [];

        foreach ($timeline as $frame) {
            $frames[] = $this->positions($frame, $lineups);
        }

        return $frames;
    }

    /**
     * @param  array<string, mixed>  $frame
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @return array{b: int, p: list<array{float, float}>}
     */
    private function positions(array $frame, array $lineups): array
    {
        $possessing = (int) $frame['s'];
        $originX = (float) $frame['x1'];
        $originY = (float) $frame['y1'];
        $ballX = (float) $frame['x2'];
        $ballY = (float) $frame['y2'];
        $isShot = in_array($frame['t'], ['shot', 'header'], true);
        $moving = $originX !== $ballX || $originY !== $ballY;

        $players = [];
        foreach ($lineups as $line) {
            $players[] = $this->drift($line, $possessing, $ballX, $ballY);
        }

        // Defenders tuck toward the man they are marking, so the back line reacts
        // to the attackers rather than only to the ball.
        $players = $this->mark($players, $possessing);

        // The nearest team-mate to where the ball started carries it.
        $carrier = $this->nearest($players, $possessing, $originX, $originY, skip: -1);
        if (isset($players[$carrier])) {
            $players[$carrier] = [...$players[$carrier], 'x' => $originX, 'y' => $originY, 'hasBall' => true];
        }

        // On a pass or carry, the receiver runs onto the destination.
        if ($moving && ! $isShot) {
            $receiver = $this->nearest($players, $possessing, $ballX, $ballY, skip: $carrier);
            if (isset($players[$receiver])) {
                $players[$receiver] = [...$players[$receiver], 'x' => $ballX, 'y' => $ballY];
            }
        }

        // The nearest defender closes the ball down.
        $presser = $this->nearest($players, 1 - $possessing, $ballX, $ballY, skip: -1);
        if (isset($players[$presser])) {
            $defender = $players[$presser];
            $players[$presser] = [
                ...$defender,
                'x' => $this->clamp($defender['x'] + self::PRESS * ($ballX - $defender['x'])),
                'y' => $this->clamp($defender['y'] + self::PRESS * ($ballY - $defender['y'])),
            ];
        }

        $p = [];
        foreach ($players as $player) {
            $p[] = [round($player['x'], 3), round($player['y'], 3)];
        }

        return ['b' => $carrier, 'p' => $p];
    }

    /**
     * @param  array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}  $line
     * @return array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}
     */
    private function drift(array $line, int $possessing, float $ballX, float $ballY): array
    {
        if ($line['gk']) {
            // The keeper holds his line and only shuffles across with the ball.
            return [
                's' => $line['s'],
                'slot' => $line['slot'],
                'x' => $line['x'],
                'y' => round($this->clamp(0.5 + 0.18 * ($ballY - 0.5)), 3),
                'gk' => true,
                'hasBall' => false,
            ];
        }

        $attacking = $line['s'] === $possessing;
        $goalX = $line['s'] === 0 ? 1.0 : 0.0; // the goal this player attacks
        $ownGoalX = 1.0 - $goalX;

        // A stable per-player nudge off the formation anchor so the lines don't
        // sit on a perfect lattice. Constant per player, so it staggers the shape
        // without adding any frame-to-frame jitter.
        $ax = $line['x'] + $this->offset($line['slot'], $line['s'], 0);
        $ay = $line['y'] + $this->offset($line['slot'], $line['s'], 1);

        $follow = $attacking ? self::FOLLOW_ATTACK : self::FOLLOW_DEFEND;
        $nx = $ax + $follow * ($ballX - $ax);
        $ny = $ay + self::BALL_SIDE * ($ballY - $ay);

        if ($attacking) {
            // Fan out wide in possession.
            $ny += self::SPREAD * ($ay - 0.5);

            // A forward makes a run in behind as the ball climbs toward the goal.
            if (abs($ax - $ownGoalX) > 0.55) {
                $nx += self::RUN * ($goalX - $nx) * abs($ballX - $ownGoalX);
            }
        } else {
            // Squeeze toward the middle out of possession.
            $ny += self::COMPACT * (0.5 - $ay);
        }

        return [
            's' => $line['s'],
            'slot' => $line['slot'],
            'x' => round($this->clamp($nx), 3),
            'y' => round($this->clamp($ny), 3),
            'gk' => false,
            'hasBall' => false,
        ];
    }

    /**
     * Nudge each defender toward the nearest attacker it is marking.
     *
     * @param  list<array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}>  $players
     * @return list<array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}>
     */
    private function mark(array $players, int $possessing): array
    {
        foreach ($players as $i => $defender) {
            if ($defender['gk'] || $defender['s'] === $possessing) {
                continue;
            }

            $man = $this->nearest($players, $possessing, $defender['x'], $defender['y'], skip: -1);
            if (isset($players[$man])) {
                $players[$i] = [
                    ...$defender,
                    'x' => $this->clamp($defender['x'] + self::MARK * ($players[$man]['x'] - $defender['x'])),
                    'y' => $this->clamp($defender['y'] + self::MARK * ($players[$man]['y'] - $defender['y'])),
                ];
            }
        }

        return $players;
    }

    /** A deterministic sub-zone offset (±0.03) for a player, stable across the match. */
    private function offset(int $slot, int $side, int $axis): float
    {
        $h = ($slot * 73856093) ^ ($side * 19349663) ^ ($axis * 83492791);

        return ((($h % 1000) + 1000) % 1000 / 1000 - 0.5) * 0.06;
    }

    /**
     * The index of the outfielder on the given side nearest to a point.
     *
     * @param  list<array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}>  $players
     */
    private function nearest(array $players, int $side, float $x, float $y, int $skip): int
    {
        $best = -1;
        $bestDist = INF;

        foreach ($players as $i => $player) {
            if ($i === $skip || $player['s'] !== $side || $player['gk']) {
                continue;
            }

            $dist = ($player['x'] - $x) ** 2 + ($player['y'] - $y) ** 2;
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $i;
            }
        }

        return $best;
    }

    private function clamp(float $value): float
    {
        return max(self::MIN, min(self::MAX, $value));
    }
}
