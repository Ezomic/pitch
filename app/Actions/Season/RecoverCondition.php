<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Season;

/**
 * A week of rest: every one of the club's players (senior and youth) recovers
 * some fitness up to full, and their form eases one step back toward neutral, so
 * a hot or cold streak fades unless matches keep feeding it.
 */
class RecoverCondition
{
    public function handle(Season $season): void
    {
        $players = Player::query()->where('user_id', $season->user_id)->get();

        foreach ($players as $player) {
            $fitness = min(Player::FITNESS_MAX, $player->fitness + Player::WEEKLY_RECOVERY);
            $form = $player->form - ($player->form <=> 0);

            $player->forceFill(['fitness' => $fitness, 'form' => $form])->save();
        }
    }
}
