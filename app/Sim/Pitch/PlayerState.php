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
    /** The reserve below which a player starts to feel it. */
    private const float TIRING_BELOW = 0.5;

    /** The share of his pace a completely spent player has lost. */
    private const float PACE_LOSS = 0.15;

    /** The share of his pace a player hurt in the match has lost, until he is replaced. */
    private const float INJURED_PACE_LOSS = 0.4;

    public Vec2 $pos;

    public Vec2 $target;

    public bool $hasBall = false;

    /**
     * Ticks left on the floor after a sliding tackle that missed. A committed
     * challenge that does not win the ball takes the defender out of the play,
     * which is the whole risk of going to ground.
     */
    public int $grounded = 0;

    /**
     * What is left in his legs, 1.0 fresh to 0.0 spent. Drained by running (see
     * Movement::moveAll) and set at kickoff from season fitness. Nothing here is
     * random: it is a pure function of the tick loop, so it is persisted with the
     * rest of the player or a resumed match would restore fresh legs.
     */
    public float $stamina = 1.0;

    /** Sent off. He takes no further part, and his side plays the rest a man down. */
    public bool $sentOff = false;

    /** Hurt in the match and playing on, slower and less accurate, until a substitute replaces him. */
    public bool $injured = false;

    /** Hurt with no substitution left to cover him: off the pitch, and his side a man down. */
    public bool $retired = false;

    /**
     * What has built up against him: legs run empty and heavy challenges (see
     * Injuries). Like stamina it is a pure function of the tick loop.
     */
    public float $strain = 0.0;

    /**
     * The strain at which he gets hurt, fixed at kickoff from the seed, or null
     * for a man who cannot be (the goalkeeper, and any match saved before
     * in-match injuries existed).
     */
    public ?float $proneness = null;

    /** How many weeks he would miss if hurt, fixed at kickoff alongside the threshold. */
    public int $injuryWeeks = 0;

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

    /** Still part of the match: on the pitch and not sent off. */
    public function isPlaying(): bool
    {
        return ! $this->sentOff && ! $this->retired;
    }

    public function isGoalkeeper(): bool
    {
        return $this->position === Position::Goalkeeper;
    }

    /**
     * How far into fatigue he is, 0 while the reserve is above half and rising to
     * 1 when it is spent. Only a depleted player is slowed or loses accuracy, so
     * a side is not handicapped for running in the first twenty minutes.
     */
    public function tiredness(): float
    {
        return max(0.0, (self::TIRING_BELOW - $this->stamina) / self::TIRING_BELOW);
    }

    /**
     * How far from his best he is for passing: as spent as a tired man, or as
     * a hurt one, whichever is worse.
     */
    public function impairment(): float
    {
        return $this->injured ? 1.0 : $this->tiredness();
    }

    /** Top speed in pitch-fractions per second, from pace, less what tiredness and injury take. */
    public function speed(): float
    {
        $speed = (0.045 + ($this->attributes->pace / 100) * 0.05) * (1.0 - self::PACE_LOSS * $this->tiredness());

        return $this->injured ? $speed * (1.0 - self::INJURED_PACE_LOSS) : $speed;
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
            'gr' => $this->grounded,
            'so' => $this->sentOff,
            'st' => $this->stamina,
            'inj' => $this->injured,
            'ret' => $this->retired,
            'sn' => $this->strain,
            'pr' => $this->proneness,
            'wk' => $this->injuryWeeks,
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
        // Absent from matches saved before sliding tackles existed.
        $player->grounded = (int) ($s['gr'] ?? 0);
        $player->sentOff = (bool) ($s['so'] ?? false);
        // Absent from matches saved before stamina existed: fresh legs.
        $player->stamina = (float) ($s['st'] ?? 1.0);
        // Absent from matches saved before in-match injuries: nobody can be hurt.
        $player->injured = (bool) ($s['inj'] ?? false);
        $player->retired = (bool) ($s['ret'] ?? false);
        $player->strain = (float) ($s['sn'] ?? 0.0);
        $player->proneness = isset($s['pr']) ? (float) $s['pr'] : null;
        $player->injuryWeeks = (int) ($s['wk'] ?? 0);

        return $player;
    }

    /**
     * A snapshot of the player in a slot after a substitute has taken it: the
     * newcomer's attributes and legs, and none of the strain or injury of the
     * man he replaced. The one place this is done, so the live substitution and
     * the replay of it cannot disagree.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<int>  $attributes
     * @return array<string, mixed>
     */
    public static function substituted(array $snapshot, array $attributes, float $stamina): array
    {
        $snapshot['attr'] = $attributes;
        $snapshot['st'] = $stamina;
        $snapshot['inj'] = false;
        $snapshot['ret'] = false;
        $snapshot['sn'] = 0.0;

        return $snapshot;
    }
}
