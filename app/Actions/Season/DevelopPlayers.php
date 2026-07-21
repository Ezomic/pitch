<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;

/**
 * One week of youth development. Each developing prospect nudges its weakest
 * attribute up, pulling its overall toward its potential; once a player reaches
 * its ceiling (or turns out to be a senior) it stops improving. Higher-potential
 * prospects keep growing after lower ones have plateaued.
 */
class DevelopPlayers
{
    private const int STEP = 5;

    private const array ATTRIBUTES = ['vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'];

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
        $weakest = null;
        $lowest = null;

        foreach (self::ATTRIBUTES as $attribute) {
            $value = $player->{$attribute};

            if ($value < $player->potential && ($lowest === null || $value < $lowest)) {
                $lowest = $value;
                $weakest = $attribute;
            }
        }

        if ($weakest === null) {
            return;
        }

        $player->forceFill([$weakest => min($player->potential, $player->{$weakest} + self::STEP)])->save();
    }
}
