<?php

declare(strict_types=1);

namespace App\Actions\Scout;

use App\Enums\ScoutStatus;
use App\Models\Scout;
use Illuminate\Validation\ValidationException;

class RecallScout
{
    /**
     * Bring a scout back off assignment.
     *
     * @throws ValidationException
     */
    public function handle(Scout $scout): void
    {
        if ($scout->status !== ScoutStatus::Scouting) {
            throw ValidationException::withMessages(['scout' => 'That scout is not out scouting.']);
        }

        $scout->forceFill([
            'status' => ScoutStatus::Idle,
            'next_delivery_on' => null,
        ])->save();
    }
}
