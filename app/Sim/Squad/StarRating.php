<?php

declare(strict_types=1);

namespace App\Sim\Squad;

/**
 * Turn club strengths into 1..5 stars by where each club sits among its peers,
 * so the stars read as "compared to the rest of this league" (or the rest of the
 * world) rather than against an absolute scale. The strongest club in a group is
 * five stars and the weakest one, in half-star steps.
 */
final class StarRating
{
    public const float MIN = 1.0;

    public const float MAX = 5.0;

    /**
     * Stars for every club in a group, keyed the same way as the input.
     *
     * @param  array<array-key, float>  $strengths  club key => strength
     * @return array<array-key, float> club key => stars, 1..5 in halves
     */
    public function rank(array $strengths): array
    {
        if ($strengths === []) {
            return [];
        }

        // A group of equally strong clubs has no pecking order to show; rate them
        // all in the middle rather than crowning an arbitrary winner.
        if (max($strengths) - min($strengths) < 0.0001) {
            return array_map(fn (): float => 3.0, $strengths);
        }

        // Rate by standing rather than by the size of the gap: one runaway club
        // would otherwise drag every rival down to a single star, when what the
        // manager wants to read is who is second best, third best and so on. Clubs
        // of equal strength share a place.
        $ordered = $strengths;
        arsort($ordered);
        $places = array_values(array_unique(array_values($ordered)));
        $last = count($places) - 1;

        $stars = [];
        foreach ($strengths as $key => $strength) {
            $place = (int) array_search($strength, $places, true);
            $stars[$key] = $this->toHalfStars(
                self::MAX - (self::MAX - self::MIN) * ($last === 0 ? 0 : $place / $last),
            );
        }

        return $stars;
    }

    private function toHalfStars(float $stars): float
    {
        return max(self::MIN, min(self::MAX, round($stars * 2) / 2));
    }
}
