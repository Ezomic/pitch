<?php

declare(strict_types=1);

namespace App\Sim\Engine;

/**
 * A dead-ball phase layered on top of open play: attacking pressure earns
 * corners and free-kicks, which a side's set-piece strength turns into goals
 * against the opposing keeper and back line. Deterministic and independent of the
 * open-play event stream, so the same inputs always add the same set-piece goals.
 */
final readonly class SetPieces
{
    /** Share of open-play shots that spill into a set-piece, plus a baseline. */
    private const float CHANCE_RATE = 0.4;

    private const int BASE_CHANCES = 1;

    /** How strongly the taker's rating converts a set-piece. */
    private const float ATTACK_WEIGHT = 0.35;

    /**
     * @return array{chances: int, goals: int}
     */
    public function resolve(int $setPiece, Defense $defence, int $seed, int $openPlayShots): array
    {
        $chances = self::BASE_CHANCES + (int) round($openPlayShots * self::CHANCE_RATE);
        $threshold = $this->clamp(($setPiece / 100) * self::ATTACK_WEIGHT - $defence->setPieceResistance());

        $rng = new Rng($seed * 31 + 17);
        $goals = 0;
        for ($i = 0; $i < $chances; $i++) {
            if ($rng->next() < $threshold) {
                $goals++;
            }
        }

        return ['chances' => $chances, 'goals' => $goals];
    }

    private function clamp(float $value, float $min = 0.02, float $max = 0.5): float
    {
        return max($min, min($max, $value));
    }
}
