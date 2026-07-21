<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;

/**
 * One week of youth development. Each developing prospect nudges an attribute up,
 * pulling its overall toward its potential; once a player reaches its ceiling (or
 * turns out to be a senior) it stops improving. A prospect with a training focus
 * develops that attribute; otherwise it works on its weakest. Higher-potential
 * prospects keep growing after lower ones have plateaued.
 */
class DevelopPlayers
{
    private const int STEP = 5;

    /**
     * @param  iterable<Player>  $players
     */
    public function handle(iterable $players): void
    {
        foreach ($players as $player) {
            if (! $player->isDeveloping()) {
                continue;
            }

            $this->grow($player);
        }
    }

    private function grow(Player $player): void
    {
        $attribute = $this->attributeToTrain($player);

        if ($attribute === null) {
            return;
        }

        $player->forceFill([$attribute => min($player->potential, $player->{$attribute} + self::STEP)])->save();
    }

    private function attributeToTrain(Player $player): ?string
    {
        $focus = $player->training_focus;

        if ($focus !== null && in_array($focus, Player::ATTRIBUTES, true) && $player->{$focus} < $player->potential) {
            return $focus;
        }

        return $this->weakest($player);
    }

    private function weakest(Player $player): ?string
    {
        $weakest = null;
        $lowest = null;

        foreach (Player::ATTRIBUTES as $attribute) {
            $value = $player->{$attribute};

            if ($value < $player->potential && ($lowest === null || $value < $lowest)) {
                $lowest = $value;
                $weakest = $attribute;
            }
        }

        return $weakest;
    }
}
