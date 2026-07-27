<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\EventType;

/**
 * The live state of a positional match: every player, the ball, who has it, and
 * the ball's flight when it is travelling (a pass or a shot in the air). Mutable;
 * the engine advances it one tick at a time.
 */
final class PitchState
{
    /** No carrier: the ball is in flight or loose. */
    public const int NO_CARRIER = -1;

    public int $possessing = 0;

    public float $clock = 0.0;

    // Ball flight. When carrierId is NO_CARRIER the ball travels from its current
    // position toward $ballTarget at $ballSpeed, bound for $ballTo (a receiver id,
    // or NO_CARRIER for a shot at goal).
    public ?Vec2 $ballTarget = null;

    public int $ballTo = self::NO_CARRIER;

    public float $ballSpeed = 0.0;

    public string $ballKind = 'idle';

    /** True while a struck shot is flying goalward and will beat the keeper. */
    public bool $ballGoal = false;

    /** Ticks the current carrier has held the ball, so decisions pace out. */
    public int $holdTicks = 0;

    /** Ticks left of a dead-ball pause after a set piece is awarded. */
    public int $deadBall = 0;

    // A set piece the current shot resolves into once it reaches goal: a corner or
    // a goal kick. Null when the shot is saved and held in open play.
    public ?EventType $pendingType = null;

    public int $pendingSide = 0;

    public ?Vec2 $pendingSpot = null;

    /**
     * @param  array<int, PlayerState>  $players  keyed by player id
     */
    public function __construct(
        public array $players,
        public Vec2 $ball,
        public int $carrierId,
    ) {}

    public function carrier(): ?PlayerState
    {
        return $this->players[$this->carrierId] ?? null;
    }

    public function inFlight(): bool
    {
        return $this->carrierId === self::NO_CARRIER;
    }

    /**
     * @return list<PlayerState>
     */
    public function side(int $side): array
    {
        return array_values(array_filter($this->players, fn (PlayerState $p) => $p->side === $side));
    }
}
