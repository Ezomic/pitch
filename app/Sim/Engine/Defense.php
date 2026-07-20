<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

/**
 * The resistance an attacking action meets, derived from the defending team's
 * tackling and pace. Defense::none() contributes nothing, reproducing the
 * original zone-only pressure exactly.
 */
final readonly class Defense
{
    private const float TACKLE_WEIGHT = 0.15;

    private const float PACE_WEIGHT = 0.08;

    private const float SHOT_WEIGHT = 0.15;

    private const string BACK = 'back';

    private const string MID = 'mid';

    private const string FORWARD = 'forward';

    /**
     * @param  array<string, float>  $tackling  line => average tackling
     * @param  array<string, float>  $pace  line => average pace
     */
    private function __construct(
        private array $tackling,
        private array $pace,
        private bool $active,
    ) {}

    public static function none(): self
    {
        return new self([], [], false);
    }

    /**
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     */
    public static function fromAttributes(array $bySlot): self
    {
        $lines = [self::BACK => [], self::MID => [], self::FORWARD => []];

        foreach (Roster::formation() as $slot => [, $position]) {
            if (! isset($bySlot[$slot])) {
                continue;
            }

            $lines[self::lineForPosition($position)][] = $bySlot[$slot];
        }

        $tackling = [];
        $pace = [];
        foreach ($lines as $line => $attributes) {
            $tackling[$line] = self::average($attributes, fn (Attributes $a) => $a->tackling);
            $pace[$line] = self::average($attributes, fn (Attributes $a) => $a->pace);
        }

        return new self($tackling, $pace, true);
    }

    public function pressureBonus(Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        return ($this->tackling[$this->lineForZone($ballZone)] / 20) * self::TACKLE_WEIGHT;
    }

    public function paceContest(int $attackerPace, Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        return (($attackerPace - $this->pace[$this->lineForZone($ballZone)]) / 20) * self::PACE_WEIGHT;
    }

    public function shotSuppression(Zone $ballZone): float
    {
        if (! $this->active) {
            return 0.0;
        }

        return ($this->tackling[$this->lineForZone($ballZone)] / 20) * self::SHOT_WEIGHT;
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
