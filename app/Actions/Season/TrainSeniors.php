<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Season;

/**
 * A week of senior training. A first-teamer set to work on an attribute nudges it
 * up toward its ceiling, but the session costs fitness, so drilling a player
 * leaves them fresher for the training ground than for Saturday. Tired, injured
 * or suspended players skip the week.
 */
class TrainSeniors
{
    public function handle(Season $season): void
    {
        $seniors = Player::query()
            ->where('user_id', $season->user_id)
            ->where('is_youth', false)
            ->whereNotNull('training_focus')
            ->where('injured_weeks', 0)
            ->where('suspended_weeks', 0)
            ->where('fitness', '>=', Player::SENIOR_TRAIN_MIN_FITNESS)
            ->get();

        foreach ($seniors as $player) {
            $this->train($player);
        }
    }

    private function train(Player $player): void
    {
        $attribute = $player->training_focus;

        if ($attribute === null || ! in_array($attribute, Player::ATTRIBUTES, true)) {
            return;
        }

        $ceiling = min(99, $player->potential);

        if ($player->{$attribute} >= $ceiling) {
            return;
        }

        $player->forceFill([
            $attribute => min($ceiling, $player->{$attribute} + Player::SENIOR_TRAIN_STEP),
            'fitness' => max(0, $player->fitness - Player::SENIOR_TRAIN_FITNESS_COST),
        ])->save();
    }
}
