<?php

declare(strict_types=1);

namespace App\Actions\Youth;

use App\Models\Player;
use Illuminate\Validation\ValidationException;

/**
 * Send an academy prospect out on loan for a spell of guaranteed minutes. While
 * away it develops faster and comes back with a lifted ceiling, but cannot be
 * fielded for the youth side or promoted until it returns.
 */
class LoanOut
{
    /**
     * @throws ValidationException
     */
    public function handle(Player $player): void
    {
        if (! $player->is_youth || $player->on_loan) {
            throw ValidationException::withMessages(['loan' => 'That prospect cannot be loaned out.']);
        }

        $player->forceFill([
            'on_loan' => true,
            'loan_weeks_remaining' => Player::LOAN_WEEKS,
        ])->save();
    }
}
