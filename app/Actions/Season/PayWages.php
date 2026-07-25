<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;

/**
 * Settle a week of finances: the club takes in its income and pays the combined
 * wages of its seniors. Overspend and the bank goes into the red, which blocks
 * new signings and, while unpaid, drains squad morale a notch a week.
 */
class PayWages
{
    public function handle(Squad $squad): void
    {
        $income = $squad->weekly_income;
        $wageBill = $squad->wageBill();

        DB::transaction(function () use ($squad, $income, $wageBill): void {
            $bank = $squad->bank - $wageBill + $income;
            $squad->forceFill(['bank' => $bank])->save();

            if ($bank < 0) {
                $this->applyUnrest($squad);
            }
        });
    }

    /** Unpaid wages knock a point off every senior's form, down to the floor. */
    private function applyUnrest(Squad $squad): void
    {
        foreach ($squad->seniors()->get() as $player) {
            $player->forceFill([
                'form' => max(Player::FORM_MIN, $player->form - 1),
            ])->save();
        }
    }
}
