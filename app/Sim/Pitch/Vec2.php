<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

/**
 * An immutable point (or vector) on the pitch in normalised 0..1 coordinates,
 * where x runs from the home goal (0) to the away goal (1) and y across the
 * width. The small helper set is all the positional engine needs to move
 * players and the ball around deterministically.
 */
final readonly class Vec2
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public function add(self $other): self
    {
        return new self($this->x + $other->x, $this->y + $other->y);
    }

    public function sub(self $other): self
    {
        return new self($this->x - $other->x, $this->y - $other->y);
    }

    public function scale(float $factor): self
    {
        return new self($this->x * $factor, $this->y * $factor);
    }

    public function length(): float
    {
        return sqrt($this->x * $this->x + $this->y * $this->y);
    }

    public function distanceTo(self $other): float
    {
        return $this->sub($other)->length();
    }

    /**
     * A step of at most $maxStep toward $target: the full remaining distance when
     * that is shorter, so a mover settles exactly on its target without overshoot.
     */
    public function moveToward(self $target, float $maxStep): self
    {
        $delta = $target->sub($this);
        $dist = $delta->length();

        if ($dist <= $maxStep || $dist === 0.0) {
            return $target;
        }

        return $this->add($delta->scale($maxStep / $dist));
    }

    public function clampToPitch(): self
    {
        return new self(
            max(0.02, min(0.98, $this->x)),
            max(0.03, min(0.97, $this->y)),
        );
    }

    /**
     * @return array{float, float}
     */
    public function toArray(): array
    {
        return [round($this->x, 4), round($this->y, 4)];
    }
}
