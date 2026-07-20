<?php

declare(strict_types=1);

namespace App\Actions\Scout;

use App\Enums\ScoutStatus;
use App\Models\Scout;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AssignScout
{
    /**
     * Send an idle scout out to work. Their first prospects arrive 2-4 weeks out.
     *
     * @throws ValidationException
     */
    public function handle(Scout $scout, CarbonImmutable $currentDate): void
    {
        if ($scout->status !== ScoutStatus::Idle) {
            throw ValidationException::withMessages(['scout' => 'Only an idle scout can be sent out.']);
        }

        $scout->forceFill([
            'status' => ScoutStatus::Scouting,
            'next_delivery_on' => $currentDate->addWeeks(random_int(2, 4)),
        ])->save();
    }
}
