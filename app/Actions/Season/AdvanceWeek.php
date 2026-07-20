<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use Carbon\CarbonImmutable;

/**
 * Advance the club calendar by one week. This is the single tick the rest of the
 * management layer hangs off: later steps (scout deliveries, youth development,
 * youth-league fixtures) are appended here so they all run on the same clock.
 */
class AdvanceWeek
{
    public function __construct(
        private readonly PlayMatchday $playMatchday = new PlayMatchday,
    ) {}

    public function handle(Season $season): void
    {
        $season->update([
            'current_date' => CarbonImmutable::parse($season->current_date)->addWeek(),
        ]);

        $this->playMatchday->handle($season);
    }
}
