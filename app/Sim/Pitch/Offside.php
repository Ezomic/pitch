<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

/**
 * The offside law, read off a position at the moment a pass is played.
 *
 * A receiver is offside when he is in the opponents' half and nearer their goal
 * line than both the ball and the second-last opponent, the keeper counting as
 * one of the defenders. Level is onside. Only the player the pass is played to
 * is judged: the engine has no other way for an attacker to become involved.
 * Pure geometry, no Rng draw, so a call never shifts the random stream.
 */
final class Offside
{
    /**
     * Whether the pass from $passer to $receiver, played now, is offside. A
     * positive $margin gives the benefit of the doubt: the receiver must be that
     * much beyond the line, which is how a passer who cannot judge a line to the
     * yard sees it. The referee's own call uses none.
     */
    public static function flags(PitchState $state, PlayerState $passer, PlayerState $receiver, float $margin = 0.0): bool
    {
        if ($state->restartTaker === $passer->id) {
            return false;
        }

        $reach = self::advance($receiver->pos, $passer->side);

        if ($reach <= 0.5 || $reach <= self::advance($passer->pos, $passer->side)) {
            return false;
        }

        $line = self::line($state, $passer->side);

        return $line !== null && $reach > $line + $margin;
    }

    /**
     * How far up the pitch the second-last opponent stands, from the attacking
     * side's point of view, or null when fewer than two opponents are on it.
     */
    public static function line(PitchState $state, int $attackingSide): ?float
    {
        $last = -INF;
        $secondLast = -INF;
        $count = 0;

        foreach ($state->players as $player) {
            if ($player->side === $attackingSide || ! $player->isPlaying()) {
                continue;
            }

            $count++;
            $advance = self::advance($player->pos, $attackingSide);

            if ($advance > $last) {
                $secondLast = $last;
                $last = $advance;
            } elseif ($advance > $secondLast) {
                $secondLast = $advance;
            }
        }

        return $count < 2 ? null : $secondLast;
    }

    private static function advance(Vec2 $pos, int $attackingSide): float
    {
        return $attackingSide === 0 ? $pos->x : 1.0 - $pos->x;
    }
}
