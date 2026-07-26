<?php

declare(strict_types=1);

namespace App\Sim\Squad;

/**
 * A deterministic motion layer for the 2D replay, DERIVED from the ball-position
 * timeline rather than simulated. The zone engine only ever tracks the ball, so
 * this nudges each of the 22 players around their formation anchor to suggest a
 * living shape: the team in possession pushes up and shifts ball-side while its
 * most advanced players hold a high outlet, the team out of possession drops into
 * a block goal-side of the ball and the nearest defender presses, the engine's
 * real actor carries the ball, and as a shot nears a couple of attackers time a
 * run into the box. Same timeline in, same motion out, so the replay stays
 * reproducible.
 *
 * Positions are emitted per timeline frame in a compact shape: `b` is the index
 * of the ball carrier and `p` holds an [x, y] pair for each player, both in the
 * same order as the lineups, so the frontend pairs identity (lineups) with
 * movement (positions) by index without shipping the identity fields again.
 */
final class PlayerMotion
{
    private const float FOLLOW_ATTACK = 0.32;

    private const float FOLLOW_DEFEND = 0.26;

    private const float BALL_SIDE = 0.22;

    /**
     * Where the out-of-possession block sits, along the line from the ball to the
     * goal it defends: 0 hugs the ball, 1 sits on its own line. Half keeps the
     * team goal-side of the ball rather than chasing it up the pitch, so the far
     * third of the pitch stays populated instead of emptying out.
     */
    private const float RETREAT = 0.5;

    private const float PRESS = 0.6;

    /** How much the team in possession fans out wide. */
    private const float SPREAD = 0.12;

    /** How much the team out of possession squeezes toward the middle. */
    private const float COMPACT = 0.18;

    /** How hard a forward runs in behind as the ball advances. */
    private const float RUN = 0.4;

    /** How strongly a defender tucks toward the nearest attacker to mark. */
    private const float MARK = 0.18;

    /** How many frames ahead a shot is felt, so runs into the box can build up. */
    private const int LOOKAHEAD = 3;

    /** How hard the box runners crash the penalty area as a shot is taken. */
    private const float BOX_RUN = 0.5;

    /** Attackers anchored within this of the goal hold as an outlet, not chase the ball. */
    private const float OUTLET_BAND = 0.35;

    /** How much a high outlet's ball-follow is damped so it holds its line. */
    private const float OUTLET_DAMP = 0.5;

    private const float MIN = 0.03;

    private const float MAX = 0.97;

    /**
     * @param  list<array<string, mixed>>  $timeline  ball-position frames
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @return list<array{b: int, p: list<array{float, float}>}>
     */
    public function build(array $timeline, array $lineups): array
    {
        $imminence = $this->shotImminence($timeline);
        $frames = [];

        foreach ($timeline as $i => $frame) {
            $frames[] = $this->positions($frame, $lineups, $imminence[$i]);
        }

        return $frames;
    }

    /**
     * Per-frame 0..1 sense of how close a shot is within the current possession,
     * so off-ball attackers can time a run into the box and arrive as the shot is
     * struck rather than after it. The shot frame itself reads 1; earlier frames
     * in the same possession ramp up toward it.
     *
     * @param  list<array<string, mixed>>  $timeline
     * @return list<float>
     */
    private function shotImminence(array $timeline): array
    {
        $n = count($timeline);
        $imminence = [];

        foreach ($timeline as $i => $frame) {
            $imminence[] = $this->imminenceAt($timeline, $i, $n, $frame);
        }

        return $imminence;
    }

    /**
     * How imminent a shot is at frame $i: 1 on the shot itself, ramping up over
     * the few frames of the same possession before it, else 0.
     *
     * @param  list<array<string, mixed>>  $timeline
     * @param  array<string, mixed>  $frame
     */
    private function imminenceAt(array $timeline, int $i, int $n, array $frame): float
    {
        if (in_array($frame['t'], ['shot', 'header'], true)) {
            return 1.0;
        }

        for ($j = $i + 1; $j <= min($i + self::LOOKAHEAD, $n - 1); $j++) {
            if (($timeline[$j]['start'] ?? false) === true) {
                break; // a fresh possession: its shot is not this one's to build to
            }

            if (in_array($timeline[$j]['t'], ['shot', 'header'], true)) {
                return 1.0 - ($j - $i - 1) / self::LOOKAHEAD;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $frame
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     * @return array{b: int, p: list<array{float, float}>}
     */
    private function positions(array $frame, array $lineups, float $imminence): array
    {
        $possessing = (int) $frame['s'];
        $originX = (float) $frame['x1'];
        $originY = (float) $frame['y1'];
        $ballX = (float) $frame['x2'];
        $ballY = (float) $frame['y2'];
        $isShot = in_array($frame['t'], ['shot', 'header'], true);
        $moving = $originX !== $ballX || $originY !== $ballY;
        $actorSlot = isset($frame['actorSlot']) ? (int) $frame['actorSlot'] : null;
        $targetSlot = isset($frame['targetSlot']) ? (int) $frame['targetSlot'] : null;

        $players = [];
        foreach ($lineups as $line) {
            $players[] = $this->drift($line, $possessing, $ballX, $ballY);
        }

        // Defenders tuck toward the man they are marking, so the back line reacts
        // to the attackers rather than only to the ball.
        $players = $this->mark($players, $possessing);

        // The player the engine actually put on the ball carries it, so the dot on
        // the ball is the same one the caption names. Only fall back to the nearest
        // team-mate when there is no real actor (a defensive win, a kick-off).
        $carrier = $this->slotIndex($lineups, $possessing, $actorSlot)
            ?? $this->nearest($players, $possessing, $originX, $originY, skip: -1);
        if (isset($players[$carrier])) {
            $players[$carrier] = [...$players[$carrier], 'x' => $originX, 'y' => $originY, 'hasBall' => true];
        }

        // On a pass or cross, the engine's actual target receives it at their spot;
        // a dribble has no target, so the carrier travels on with the ball instead
        // of a phantom receiver appearing on the destination.
        $receiver = -1;
        if ($moving && ! $isShot && $targetSlot !== null) {
            $receiver = $this->slotIndex($lineups, $possessing, $targetSlot)
                ?? $this->nearest($players, $possessing, $ballX, $ballY, skip: $carrier);
            if (isset($players[$receiver])) {
                $players[$receiver] = [...$players[$receiver], 'x' => $ballX, 'y' => $ballY];
            }
        }

        // As a shot nears, one or two off-ball attackers time a run into the box so
        // the strike arrives into a populated area rather than out of nowhere.
        $players = $this->boxRuns($players, $possessing, $imminence, [$carrier, $receiver]);

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

        $ny = $ay + self::BALL_SIDE * ($ballY - $ay);
        $nx = $ax;

        if ($attacking) {
            // The most advanced attackers hold their line as a high outlet instead
            // of all dropping onto the ball, so there is always a target up the
            // pitch and the team keeps a shape rather than herding to the ball.
            $follow = abs($ax - $goalX) < self::OUTLET_BAND
                ? self::FOLLOW_ATTACK * self::OUTLET_DAMP
                : self::FOLLOW_ATTACK;
            $nx = $ax + $follow * ($ballX - $ax);

            // Fan out wide in possession.
            $ny += self::SPREAD * ($ay - 0.5);

            // A forward makes a run in behind as the ball climbs toward the goal.
            if (abs($ax - $ownGoalX) > 0.55) {
                $nx += self::RUN * ($goalX - $nx) * abs($ballX - $ownGoalX);
            }
        } else {
            // Drop into a block goal-side of the ball rather than chasing its exact
            // position up the pitch, so the defending shape holds and the third of
            // the pitch nearest its own goal does not empty out.
            $blockX = $ballX + self::RETREAT * ($ownGoalX - $ballX);
            $nx = $ax + self::FOLLOW_DEFEND * ($blockX - $ax);

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

    /**
     * As a shot nears, send the two furthest-forward off-ball attackers on a run
     * to the near and far post, scaled by how imminent the shot is, so a strike
     * lands in a busy box. Players already committed to the ball are left alone.
     *
     * @param  list<array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}>  $players
     * @param  list<int>  $skip  indices on the ball (carrier, receiver)
     * @return list<array{s: int, slot: int, x: float, y: float, gk: bool, hasBall: bool}>
     */
    private function boxRuns(array $players, int $possessing, float $imminence, array $skip): array
    {
        if ($imminence <= 0.0) {
            return $players;
        }

        $runX = $possessing === 0 ? 0.9 : 0.1;

        $advance = [];
        foreach ($players as $i => $player) {
            if (in_array($i, $skip, true) || $player['gk'] || $player['s'] !== $possessing) {
                continue;
            }
            $advance[$i] = $possessing === 0 ? $player['x'] : 1.0 - $player['x'];
        }
        arsort($advance);

        $posts = [[$runX, 0.4], [$runX, 0.6]];
        $post = 0;
        foreach (array_keys($advance) as $i) {
            if ($post >= count($posts)) {
                break;
            }
            [$tx, $ty] = $posts[$post];
            $player = $players[$i];
            $players[$i] = [
                ...$player,
                'x' => $this->clamp($player['x'] + self::BOX_RUN * $imminence * ($tx - $player['x'])),
                'y' => $this->clamp($player['y'] + self::BOX_RUN * $imminence * ($ty - $player['y'])),
            ];
            $post++;
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
     * The lineup index of a given side's slot, or null when there is no such slot
     * (an unknown or absent engine actor).
     *
     * @param  list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>  $lineups
     */
    private function slotIndex(array $lineups, int $side, ?int $slot): ?int
    {
        if ($slot === null) {
            return null;
        }

        foreach ($lineups as $i => $line) {
            if ($line['s'] === $side && $line['slot'] === $slot) {
                return $i;
            }
        }

        return null;
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
