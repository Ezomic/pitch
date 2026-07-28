<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;

/**
 * One player's live state during a positional match: an identity and formation
 * anchor that never change, and a position and target that the tick loop moves
 * each step. Mutable on purpose, like the engine's other state objects, so the
 * loop can advance 22 players a tick without reallocating value objects.
 *
 * The id is unique across both teams (side * 100 + slot) so the ball carrier can
 * be referenced by a single int.
 */
final class PlayerState
{
    public Vec2 $pos;

    public Vec2 $target;

    public bool $hasBall = false;

    public function __construct(
        public readonly int $id,
        public readonly int $side,
        public readonly int $slot,
        public readonly Position $position,
        public readonly Vec2 $anchor,
        public readonly Attributes $attributes,
        ?Vec2 $pos = null,
    ) {
        $this->pos = $pos ?? $anchor;
        $this->target = $this->pos;
    }

    public static function id(int $side, int $slot): int
    {
        return $side * 100 + $slot;
    }

    public function isGoalkeeper(): bool
    {
        return $this->position === Position::Goalkeeper;
    }

    /** Top speed in pitch-fractions per second, from pace. */
    public function speed(): float
    {
        return 0.045 + ($this->attributes->pace / 100) * 0.05;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $a = $this->attributes;

        return [
            'id' => $this->id,
            'side' => $this->side,
            'slot' => $this->slot,
            'pos' => $this->position->value,
            'anchor' => $this->anchor->pair(),
            'attr' => [$a->vision, $a->passing, $a->dribbling, $a->finishing, $a->tackling, $a->pace],
            'p' => $this->pos->pair(),
            't' => $this->target->pair(),
            'hb' => $this->hasBall,
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    public static function fromSnapshot(array $s): self
    {
        /** @var array{int, int, int, int, int, int} $attr */
        $attr = $s['attr'];
        $player = new self(
            (int) $s['id'],
            (int) $s['side'],
            (int) $s['slot'],
            Position::from($s['pos']),
            Vec2::fromPair($s['anchor']),
            new Attributes(...$attr),
            Vec2::fromPair($s['p']),
        );
        $player->target = Vec2::fromPair($s['t']);
        $player->hasBall = (bool) $s['hb'];

        return $player;
    }
}
