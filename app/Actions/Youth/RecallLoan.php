<?php

declare(strict_types=1);

namespace App\Actions\Youth;

use App\Models\Player;
use Illuminate\Validation\ValidationException;

/**
 * Cut a loan short and bring the prospect home. Recalling early forgoes the
 * ceiling lift a full spell would have earned.
 */
class RecallLoan
{
    /**
     * @throws ValidationException
     */
    public function handle(Player $player): void
    {
        if (! $player->on_loan) {
            throw ValidationException::withMessages(['loan' => 'That prospect is not out on loan.']);
        }

        $player->forceFill([
            'on_loan' => false,
            'loan_weeks_remaining' => 0,
        ])->save();
    }
}
