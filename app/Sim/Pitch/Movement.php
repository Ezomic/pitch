<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Position;

/**
 * Where the 22 players want to be, and moving them there.
 *
 * Everything off the ball lives here: the shape a side holds in possession, the
 * block it drops into out of possession, who picks up whom, and the staggered
 * reaction lag that stops the whole team turning on the same tick.
 *
 * Not one random draw is taken in this file. Off-ball movement is deliberately
 * a pure function of the state and the tick counter (see runWindow, which
 * staggers runs by player id rather than by rolling for them), which is what
 * lets the shape break and reform without ever disturbing the Rng stream the
 * ball's decisions depend on.
 */
final class Movement
{
    public const float TICK = 0.2;              // seconds of play per tick

    public const int TRAIL_TICKS = 6;           // ticks of ball history kept for reaction lag

    private const float MARK = 0.45;            // how hard a defender shades onto its man

    // How far the out-of-possession block drops off the ball toward its own goal.
    // Lower means it engages higher up the pitch.
    private const float BLOCK_RETREAT = 0.30;

    // How far a player in the block travels from its formation anchor toward
    // that line. Higher means the shape actually gets there.
    private const float BLOCK_STEP = 0.75;

    // How far the whole block steps up the pitch behind the ball in possession.
    private const float PUSH_SCALE = 0.85;

    public function setTargets(PitchState $state, int $tick): void
    {
        $ballX = $state->ball->x;

        foreach ($state->players as $player) {
            if ($player->isGoalkeeper()) {
                $goalX = $player->side === 0 ? 0.03 : 0.97;
                $player->target = new Vec2($goalX, 0.5 + ($state->ball->y - 0.5) * 0.4);

                continue;
            }

            if ($player->id === $state->carrierId) {
                $goal = Geometry::goalOf($player->side);

                // A wide player in the attacking third attacks the byline to cross,
                // rather than cutting inside, unless he is clean through, in which
                // case he drives straight at goal to shoot.
                if (abs($player->pos->y - 0.5) > 0.22 && $player->pos->distanceTo($goal) < 0.5
                    && ! Geometry::clearRunToGoal($state, $player, $goal)) {
                    $bylineX = $player->side === 0 ? 0.95 : 0.05;
                    $wideY = $player->pos->y < 0.5 ? 0.12 : 0.88;
                    $player->target = new Vec2($bylineX, $wideY);

                    continue;
                }

                // Otherwise drive at goal, veering wide of a closing defender rather
                // than running straight into the tackle.
                $defender = Geometry::nearestOpponent($state, $player->pos, $player->side);
                if ($defender !== null && $player->pos->distanceTo($defender->pos) < 0.11) {
                    $away = $player->pos->y >= $defender->pos->y ? 1.0 : -1.0;
                    $player->target = (new Vec2($goal->x, $player->pos->y + $away * 0.18))->clampToPitch();
                } else {
                    $player->target = $goal;
                }

                continue;
            }

            if ($state->inFlight() && $player->id === $state->ballTo && $state->ballTarget !== null) {
                // A receiver runs onto the ball.
                $player->target = $state->ballTarget;

                continue;
            }

            if ($player->side === $state->possessing) {
                // In possession the whole block steps up the pitch as the team
                // builds: the back line and midfield push up toward the halfway
                // line, while the forwards play high on the shoulder of the
                // opponent's last defender, ready to receive around their defence.
                // Each player reads a slightly older ball, so they set off in their
                // own time rather than the shape sliding as one body.
                $seen = $state->laggedBall($this->reactionLag($player));
                $bias = $this->mentalityBias($state, $player->side);
                $ballAdvance = $player->side === 0 ? $seen->x : 1.0 - $seen->x;
                $push = max(0.0, $ballAdvance - 0.15) * (1.0 + 0.22 * $bias) * self::PUSH_SCALE;
                $dir = $player->side === 0 ? 1.0 : -1.0;

                if ($player->position === Position::Forward) {
                    $lineX = $this->opponentLastLineX($state, $player->side);
                    $shoulderX = $lineX - $dir * 0.10; // sit off the last man, not glued on
                    $anchorPushX = $player->anchor->x + $dir * $push * 0.45;
                    $targetX = $dir > 0 ? max($anchorPushX, $shoulderX) : min($anchorPushX, $shoulderX);
                } else {
                    $lift = $push * ($player->position === Position::Midfielder ? 0.85 : 0.6);
                    $targetX = $player->anchor->x + $dir * $lift;

                    if ($player->position === Position::Defender) {
                        // Hold a back line no higher than around the halfway line so
                        // the team is not caught square on the turnover.
                        $targetX = $dir > 0 ? min($targetX, 0.52) : max($targetX, 0.48);
                    }
                }

                $base = new Vec2(
                    $targetX,
                    $player->anchor->y + ($seen->y - 0.5) * 0.2 * $this->shadeFactor($player),
                );

                // Off-ball run: every so often, if the space ahead toward goal is
                // free of a defender, a forward or midfielder bursts into it to be
                // played in behind, rather than holding the line. Runs are staggered
                // per player and deterministic (no random draw), so the shape breaks
                // and reforms instead of everyone lurching at once.
                if ($player->id !== $state->carrierId
                    && $player->position === Position::Forward
                    && $this->runWindow($player, $tick, $this->mentalityBias($state, $player->side))) {
                    $goal = Geometry::goalOf($player->side);
                    $ahead = $base->moveToward($goal, 0.14);
                    if ($this->spaceFreeAt($state, $ahead, $player->side)) {
                        $base = $ahead;
                    }
                }

                $player->target = $base->clampToPitch();

                continue;
            }

            // Out of possession: the nearest man presses the carrier; the rest
            // hold a compact block goal-side of the ball, each shading onto the
            // runner it is responsible for so the pass into him is screened.
            $carrier = $state->carrier();
            if ($carrier !== null && $this->isNearestDefender($state, $player, $carrier->pos)) {
                $player->target = $carrier->pos;

                continue;
            }

            // Leave the forwards high as a counter outlet. Rather than tracking back
            // into the block, they hold up the pitch between the opponent's midfield
            // and defensive lines, so winning the ball springs an immediate attack
            // with a target already in behind instead of building from deep.
            if ($player->position === Position::Forward) {
                $seen = $state->laggedBall($this->reactionLag($player));
                $line = $this->opponentLastLineX($state, $player->side);
                $holdX = $player->side === 0
                    ? min(max($line - 0.08, 0.52), 0.66)
                    : max(min($line + 0.08, 0.48), 0.34);
                $player->target = (new Vec2(
                    $holdX,
                    $player->anchor->y + ($seen->y - 0.5) * 0.1 * $this->shadeFactor($player),
                ))->clampToPitch();

                continue;
            }

            // Hold a compact block goal-side of the ball. A meaningfully higher line
            // needs timed off-ball runs to exploit the space it concedes, which the
            // engine does not model yet, so stepping it up here only strangles the
            // build-up; that is deferred to the off-ball-runs stage.
            $seen = $state->laggedBall($this->reactionLag($player));
            $ownGoalX = $player->side === 0 ? 0.0 : 1.0;
            $blockX = $seen->x + ($ownGoalX - $seen->x) * self::BLOCK_RETREAT;
            $base = new Vec2(
                $player->anchor->x + ($blockX - $player->anchor->x) * self::BLOCK_STEP,
                $player->anchor->y + ($seen->y - 0.5) * 0.3 * $this->shadeFactor($player),
            );

            $man = $this->markAssignment($state, $player);
            if ($man !== null) {
                $dir = $player->side === 0 ? -1.0 : 1.0; // toward own goal
                $goalSide = new Vec2($man->pos->x + $dir * 0.03, $man->pos->y);
                $base = new Vec2(
                    $base->x + ($goalSide->x - $base->x) * self::MARK,
                    $base->y + ($goalSide->y - $base->y) * self::MARK,
                );
            }

            $player->target = $base->clampToPitch();
        }
    }

    /**
     * A deterministic, staggered window during which a player is minded to make a
     * run: a few of them at any moment, spread out by id, so runs come "every so
     * often" rather than all together. No random draw, to keep determinism.
     */
    public function runWindow(PlayerState $player, int $tick, int $bias = 0): bool
    {
        return (intdiv($tick, 10) + $player->id) % 12 < 2 + $bias;
    }

    /**
     * How many ticks behind the ball a player reads the game, fixed per player (from
     * its id, no random draw). Team-mates therefore start adjusting at different
     * moments, so the shape shifts raggedly like a real team instead of every player
     * turning on the same tick.
     */
    public function reactionLag(PlayerState $player): int
    {
        return ($player->id * 3) % self::TRAIL_TICKS;
    }

    /**
     * How strongly a player shades across with the ball, as a multiplier around 1.
     * Fixed per player, so the whole line does not travel exactly the same distance.
     */
    public function shadeFactor(PlayerState $player): float
    {
        return 0.75 + ($player->id % 5) * 0.125; // 0.75 .. 1.25
    }

    public function mentalityBias(PitchState $state, int $side): int
    {
        return match ($state->mentality($side)) {
            'attacking' => 1,
            'defensive' => -1,
            default => 0,
        };
    }

    public function spaceFreeAt(PitchState $state, Vec2 $point, int $side): bool
    {
        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            if ($player->pos->distanceTo($point) < 0.07) {
                return false;
            }
        }

        return true;
    }

    public function moveAll(PitchState $state): void
    {
        foreach ($state->players as $player) {
            $player->pos = $player->pos->moveToward($player->target, $player->speed() * self::TICK)->clampToPitch();
        }
    }

    /**
     * The single attacker this defender is responsible for: its nearest man, but
     * only when it is the closest available defender to him (the presser is busy
     * on the carrier), so each runner is picked up once instead of the whole line
     * collapsing onto the ball.
     */
    public function markAssignment(PitchState $state, PlayerState $defender): ?PlayerState
    {
        $man = Geometry::nearestOpponent($state, $defender->pos, $defender->side);
        if ($man === null || $defender->pos->distanceTo($man->pos) > 0.35) {
            return null;
        }

        $carrier = $state->carrier();
        $owner = null;
        $ownerDist = INF;

        foreach ($state->players as $other) {
            if ($other->side !== $defender->side || $other->isGoalkeeper()) {
                continue;
            }

            if ($carrier !== null && $this->isNearestDefender($state, $other, $carrier->pos)) {
                continue; // the presser is committed to the carrier
            }

            $dist = $other->pos->distanceTo($man->pos);
            if ($dist < $ownerDist) {
                $ownerDist = $dist;
                $owner = $other;
            }
        }

        return $owner !== null && $owner->id === $defender->id ? $man : null;
    }

    public function isNearestDefender(PitchState $state, PlayerState $defender, Vec2 $point): bool
    {
        $nearest = Geometry::nearestOpponent($state, $point, 1 - $defender->side);

        return $nearest !== null && $nearest->id === $defender->id;
    }

    /**
     * The x of the opponent's deepest outfielder, the last line an attacking side
     * plays off. For side 0 (attacking toward x=1) that is the largest opponent x;
     * for side 1 the smallest.
     */
    public function opponentLastLineX(PitchState $state, int $attackingSide): float
    {
        $line = null;

        foreach ($state->players as $player) {
            if ($player->side === $attackingSide || $player->isGoalkeeper()) {
                continue;
            }

            $line = $line === null
                ? $player->pos->x
                : ($attackingSide === 0 ? max($line, $player->pos->x) : min($line, $player->pos->x));
        }

        return $line ?? ($attackingSide === 0 ? 0.9 : 0.1);
    }
}
