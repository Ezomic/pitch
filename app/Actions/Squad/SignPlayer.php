<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sign a free agent: pay their value out of the bank and bring them into the
 * user's owned squad, where they become fieldable.
 */
class SignPlayer
{
    /**
     * @throws ValidationException
     */
    public function handle(Squad $squad, Player $player): void
    {
        if (! $player->is_free_agent) {
            throw ValidationException::withMessages(['transfer' => 'That player is not on the market.']);
        }

        $price = $player->value();

        if ($squad->bank < $price) {
            throw ValidationException::withMessages(['transfer' => 'Not enough money in the bank.']);
        }

        DB::transaction(function () use ($squad, $player, $price): void {
            $player->forceFill(['user_id' => $squad->user_id, 'is_free_agent' => false])->save();
            $squad->forceFill(['bank' => $squad->bank - $price])->save();
        });
    }
}
