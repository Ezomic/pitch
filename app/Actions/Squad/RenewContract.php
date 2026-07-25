<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Extend an owned senior's deal back to a full term, paying a signing-on fee out
 * of the bank. Renewing before the contract lapses is how a squad keeps the
 * players it has developed.
 */
class RenewContract
{
    /** The signing-on fee is this many weeks of the player's wage. */
    private const int FEE_WEEKS = 12;

    /**
     * @throws ValidationException
     */
    public function handle(Squad $squad, Player $player): void
    {
        if ($player->user_id !== $squad->user_id || $player->is_youth) {
            throw ValidationException::withMessages(['contract' => 'You cannot renew that player.']);
        }

        $fee = $player->weeklyWage() * self::FEE_WEEKS;

        if ($squad->bank < $fee) {
            throw ValidationException::withMessages(['contract' => 'Not enough money to renew that contract.']);
        }

        DB::transaction(function () use ($squad, $player, $fee): void {
            $player->forceFill(['contract_years' => Player::DEFAULT_CONTRACT_YEARS])->save();
            $squad->forceFill(['bank' => $squad->bank - $fee])->save();
        });
    }
}
