<?php

declare(strict_types=1);

namespace App\Actions\Scout;

use App\Enums\ScoutStatus;
use App\Models\Scout;
use App\Models\Squad;
use Illuminate\Validation\ValidationException;

class HireScout
{
    /**
     * Bring an available scout onto the books, paying their fee from the budget.
     *
     * @throws ValidationException
     */
    public function handle(Scout $scout, Squad $squad): void
    {
        if ($scout->status !== ScoutStatus::Available) {
            throw ValidationException::withMessages(['scout' => 'That scout is not available to hire.']);
        }

        if ($squad->budget < $scout->cost()) {
            throw ValidationException::withMessages(['scout' => 'You cannot afford that scout.']);
        }

        $squad->decrement('budget', $scout->cost());
        $scout->forceFill(['status' => ScoutStatus::Idle])->save();
    }
}
