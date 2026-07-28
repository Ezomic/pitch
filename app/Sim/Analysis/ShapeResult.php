<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * Mean player positions from a batch and the shape metrics they imply. Positions
 * are raw pitch coordinates (home attacks toward x=1, away toward x=0); the shape
 * metrics are oriented up each side's own attacking axis so the two are directly
 * comparable.
 *
 * @phpstan-type Shape array{lineHeight: float, length: float, width: float, compactness: float}
 */
final readonly class ShapeResult
{
    /**
     * @param  array<int, array{float, float}>  $positions  index 0..21 => mean [x, y]
     */
    public function __construct(
        public array $positions,
        public int $matches,
    ) {}

    /**
     * Mean positions for a side, keyed by slot (0 = keeper, 1..10 outfield).
     *
     * @return array<int, array{float, float}>
     */
    public function side(int $side): array
    {
        $base = $side === 0 ? 0 : 11;
        $out = [];
        for ($slot = 0; $slot <= 10; $slot++) {
            $out[$slot] = $this->positions[$base + $slot];
        }

        return $out;
    }

    /**
     * @return Shape
     */
    public function shape(int $side): array
    {
        $advances = [];
        $ys = [];
        foreach ($this->side($side) as $slot => [$x, $y]) {
            if ($slot === 0) {
                continue; // keeper is not part of the outfield shape
            }
            $advances[] = $side === 0 ? $x : 1.0 - $x;
            $ys[] = $y;
        }

        if ($advances === [] || $ys === []) {
            return ['lineHeight' => 0.0, 'length' => 0.0, 'width' => 0.0, 'compactness' => 0.0];
        }

        sort($advances);
        $backFour = array_slice($advances, 0, 4);

        return [
            'lineHeight' => array_sum($backFour) / count($backFour),
            'length' => max($advances) - min($advances),
            'width' => max($ys) - min($ys),
            'compactness' => (max($advances) - min($advances)) * (max($ys) - min($ys)),
        ];
    }
}
