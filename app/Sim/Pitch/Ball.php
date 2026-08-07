<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

/**
 * How fast the ball travels once it has been struck.
 *
 * A home of their own for these two, so the engine and the set pieces can both
 * reach them without either having to reach into the other. Struck attempts
 * travel faster than a played pass, which is the whole of the model: a shot
 * gives defenders and the keeper less time to intervene than a pass does.
 */
final class Ball
{
    /** Ball speed in flight for a pass, in pitch-fractions per second. */
    public const float PASS_SPEED = 0.34;

    /** A struck attempt on goal, whether from open play or a dead ball. */
    public const float SHOT_SPEED = 0.55;
}
