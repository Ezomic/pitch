<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Decision;

final class DecisionMaker
{
    /**
     * How many of the available options a player can evaluate, from their vision.
     * Vision 30 sees 2 options, vision 80 sees 5.
     */
    public function visibleCount(int $vision, int $total): int
    {
        $count = intdiv($vision, 15);

        return max(1, min($count, $total));
    }

    /**
     * Pick the best option the player can actually see. The visible subset is a
     * deterministic shuffle truncated to the vision count, so a higher-vision
     * player always sees a superset of a lower-vision player's options at the
     * same tick. The gap between the chosen and the best available threat is the
     * signal this whole spike exists to measure.
     *
     * @param  list<Option>  $options
     */
    public function decide(array $options, int $vision, Rng $rng): Choice
    {
        $total = count($options);
        $bestAvailable = $this->bestThreat($options);

        $shuffled = $this->shuffle($options, $rng);
        $visibleCount = $this->visibleCount($vision, $total);
        $visible = array_slice($shuffled, 0, $visibleCount);

        $chosen = $visible[0];
        foreach ($visible as $option) {
            if ($option->threat > $chosen->threat) {
                $chosen = $option;
            }
        }

        return new Choice(
            $chosen,
            new Decision($visibleCount, $total, $chosen->threat, $bestAvailable),
        );
    }

    /**
     * @param  list<Option>  $options
     * @return list<Option>
     */
    private function shuffle(array $options, Rng $rng): array
    {
        for ($i = count($options) - 1; $i > 0; $i--) {
            $j = $rng->below($i + 1);
            [$options[$i], $options[$j]] = [$options[$j], $options[$i]];
        }

        return array_values($options);
    }

    /**
     * @param  list<Option>  $options
     */
    private function bestThreat(array $options): float
    {
        $best = $options[0]->threat;
        foreach ($options as $option) {
            if ($option->threat > $best) {
                $best = $option->threat;
            }
        }

        return $best;
    }
}
