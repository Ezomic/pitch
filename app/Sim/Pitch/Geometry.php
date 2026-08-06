<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Zone;

/**
 * The spatial questions the engine asks over and over: where the goals and the
 * boxes are, who is nearest to a point or to a passing lane, and how a
 * continuous position maps onto the coarse zone grid the rest of the app reads.
 *
 * Pure geometry. Nothing here touches the Rng or mutates state, which is what
 * makes it safe to lift out of the tick loop without changing a single match.
 */
final class Geometry
{
    public static function goalOf(int $side): Vec2
    {
        return $side === 0 ? new Vec2(1.0, 0.5) : new Vec2(0.0, 0.5);
    }

    public static function inPenaltyBox(Vec2 $pos, int $attackingSide): bool
    {
        $inX = $attackingSide === 0 ? $pos->x > 0.83 : $pos->x < 0.17;

        return $inX && $pos->y > 0.21 && $pos->y < 0.79;
    }

    public static function penaltySpot(int $attackingSide): Vec2
    {
        return new Vec2($attackingSide === 0 ? 0.88 : 0.12, 0.5);
    }

    public static function spaceAheadOf(PlayerState $runner, Vec2 $goal): Vec2
    {
        return $runner->pos->moveToward($goal, 0.1);
    }

    /**
     * How dangerous it is to have the ball at a point: 1 on the goal line, fading
     * to 0 by ~0.7 of the pitch away. A shared yardstick for comparing the options
     * a carrier weighs, so a decision can be audited after the fact.
     */
    public static function danger(Vec2 $pos, int $side): float
    {
        $dist = $pos->distanceTo(self::goalOf($side));

        return max(0.0, min(1.0, 1.0 - $dist / 0.7));
    }

    public static function nearestOpponent(PitchState $state, Vec2 $point, int $side): ?PlayerState
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = $player->pos->distanceTo($point);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return $best;
    }

    /**
     * The opponent best placed to intercept a pass along the segment a→b, and its
     * distance to that lane.
     *
     * @return array{PlayerState|null, float}
     */
    public static function nearestOpponentToSegment(PitchState $state, Vec2 $a, Vec2 $b, int $side): array
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = self::segmentDistance($player->pos, $a, $b);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return [$best, $bestDist];
    }

    public static function segmentDistance(Vec2 $point, Vec2 $a, Vec2 $b): float
    {
        $ab = $b->sub($a);
        $lengthSq = $ab->x * $ab->x + $ab->y * $ab->y;

        if ($lengthSq <= 0.0) {
            return $point->distanceTo($a);
        }

        $t = (($point->x - $a->x) * $ab->x + ($point->y - $a->y) * $ab->y) / $lengthSq;
        $t = max(0.0, min(1.0, $t));

        return $point->distanceTo($a->add($ab->scale($t)));
    }

    /** A continuous position on the coarse zone grid the rest of the app reads. */
    public static function zone(Vec2 $point, int $side): Zone
    {
        $advance = $side === 0 ? $point->x : 1.0 - $point->x;

        return new Zone(
            max(0, min(Zone::MAX_X, (int) round($advance * Zone::MAX_X))),
            max(0, min(Zone::MAX_Y, (int) round($point->y * Zone::MAX_Y))),
        );
    }

    /**
     * True when the carrier has beaten the last line: close enough to goal, with
     * no outfielder level with or goal-side of him. Read by the shape (a wide man
     * clean through drives at goal rather than heading for the byline) and by the
     * decision (he shoots rather than crossing or laying it off).
     */
    public static function clearRunToGoal(PitchState $state, PlayerState $carrier, Vec2 $goal): bool
    {
        $carrierToGoal = $carrier->pos->distanceTo($goal);
        if ($carrierToGoal > 0.5) {
            return false; // too far out to be through on goal
        }

        foreach ($state->players as $opponent) {
            if ($opponent->side === $carrier->side || $opponent->isGoalkeeper()) {
                continue;
            }

            if ($opponent->pos->distanceTo($goal) < $carrierToGoal + 0.05) {
                return false; // an outfielder is level with or goal-side of the carrier
            }
        }

        return true;
    }
}
