<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use App\Models\SquadPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sell an owned player back to the market: bank their value, drop them from the
 * XI, and return them to the free-agent pool.
 */
class SellPlayer
{
    /**
     * @throws ValidationException
     */
    public function handle(Squad $squad, Player $player): void
    {
        if ($player->user_id !== $squad->user_id || $player->is_youth) {
            throw ValidationException::withMessages(['transfer' => 'You cannot sell that player.']);
        }

        $price = $player->value();

        DB::transaction(function () use ($squad, $player, $price): void {
            SquadPlayer::query()->where('player_id', $player->id)->delete();
            $player->forceFill(['user_id' => null, 'is_free_agent' => true])->save();
            $squad->forceFill(['bank' => $squad->bank + $price])->save();
        });
    }
}
