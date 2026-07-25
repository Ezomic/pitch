<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

/**
 * The resistance an attacking action meets, derived from the defending team's
 * tackling and pace, its formation (how many cover each band) and its mentality
 * (defenceBias). Defense::none() contributes nothing, reproducing the original
 * zone-only pressure exactly, as does a balanced formation at bias 1.0.
 */
final readonly class Defense
{
    private const float TACKLE_WEIGHT = 0.15;

    private const float PACE_WEIGHT = 0.08;

    private const float SHOT_WEIGHT = 0.15;

    /**
     * How strongly the keeper's shot-stopping suppresses a shot becoming a goal.
     * Sized so the gap between a poor and an elite keeper is worth several goals a
     * season, cleanly separate from the outfield back line's contribution.
     */
    private const float KEEPER_WEIGHT = 0.20;

    private const string BACK = 'back';

    private const string MID = 'mid';

    private const string FORWARD = 'forward';

    /**
     * @param  array<string, float>  $tackling  line => average tackling
     * @param  array<string, float>  $pace  line => average pace
     * @param  array<string, float>  $coverage  line => 0..1 fraction of a full line
     */
    private function __construct(
        private array $tackling,
        private array $pace,
        private array $coverage,
        private float $defenceBias,
        private bool $active,
        private int $goalkeeping = 0,
    ) {}

    public static function none(): self
    {
        return new self([], [], [], 1.0, false);
    }

    /**
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     */
    public static function fromAttributes(array $bySlot, ?Formation $formation = null, float $defenceBias = 1.0, int $goalkeeping = 0): self
    {
        $formation ??= Formation::balanced();

        $lines = [self::BACK => [], self::MID => [], self::FORWARD => []];

        foreach ($formation->layout as $slot => [, $position]) {
            if (! isset($bySlot[$slot])) {
                continue;
            }

            $lines[self::lineForPosition($position)][] = $bySlot[$slot];
        }

        $tackling = [];
        $pace = [];
        $coverage = [];
        foreach ($lines as $line => $attributes) {
            $tackling[$line] = self::average($attributes, fn (Attributes $a) => $a->tackling);
            $pace[$line] = self::average($attributes, fn (Attributes $a) => $a->pace);
            // Only the back line's numbers matter for defending the final third,
            // where chances are conceded; forwards and midfielders press rather
            // than hold, so their coverage is treated as full. A back four is the
            // baseline (1.0), so a back three is leakier and a back five tighter.
            $coverage[$line] = $line === self::BACK ? count($attributes) / 4 : 1.0;
        }

        return new self($tackling, $pace, $coverage, $defenceBias, true, $goalkeeping);
    }

    public function pressureBonus(Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        $line = $this->lineForZone($ballZone);

        return ($this->tackling[$line] / 100) * $this->coverage[$line] * self::TACKLE_WEIGHT * $this->defenceBias;
    }

    public function paceContest(int $attackerPace, Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        $line = $this->lineForZone($ballZone);

        return (($attackerPace - $this->pace[$line]) / 100) * self::PACE_WEIGHT * $this->coverage[$line];
    }

    public function shotSuppression(Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        $line = $this->lineForZone($ballZone);

        $backLine = ($this->tackling[$line] / 100) * $this->coverage[$line] * self::SHOT_WEIGHT * $this->defenceBias;

        // The keeper stops shots wherever they come from, so its save weight is
        // added on top of the outfield line and does not scale with the ball zone.
        return $backLine + ($this->goalkeeping / 100) * self::KEEPER_WEIGHT;
    }

    /**
     * How well the defence smothers a dead-ball delivery: the keeper claiming it
     * and the back line clearing it. Zero for Defense::none(), so a bare defence
     * offers no set-piece resistance.
     */
    public function setPieceResistance(): float
    {
        if (! $this->active) {
            return 0.0;
        }

        $keeper = ($this->goalkeeping / 100) * 0.20;
        $backLine = ($this->tackling[self::BACK] / 100) * $this->coverage[self::BACK] * 0.12;

        return $keeper + $backLine;
    }

    private function lineForZone(Zone $ballZone): string
    {
        return match (true) {
            $ballZone->x >= 4 => self::BACK,
            $ballZone->x >= 2 => self::MID,
            default => self::FORWARD,
        };
    }

    private static function lineForPosition(Position $position): string
    {
        return match ($position) {
            Position::Forward => self::FORWARD,
            Position::Midfielder => self::MID,
            default => self::BACK,
        };
    }

    /**
     * @param  list<Attributes>  $attributes
     */
    private static function average(array $attributes, callable $pick): float
    {
        if ($attributes === []) {
            return 0.0;
        }

        $sum = 0;
        foreach ($attributes as $a) {
            $sum += $pick($a);
        }

        return $sum / count($attributes);
    }
}
