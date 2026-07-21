<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use Illuminate\Support\Facades\DB;

/**
 * Close the finished campaign and open the next one. The completed season is kept
 * (stamped completed_at) for history; a fresh season with the next number gets a
 * new fixture list. The squad and academy carry over untouched.
 */
class RolloverSeason
{
    public function __construct(
        private readonly ScheduleSeason $scheduleSeason = new ScheduleSeason,
    ) {}

    public function handle(Season $current): Season
    {
        return DB::transaction(function () use ($current): Season {
            $current->forceFill(['completed_at' => now()])->save();

            $next = Season::create([
                'user_id' => $current->user_id,
                'number' => $current->number + 1,
                'starts_on' => Season::STARTS_ON,
                'current_date' => Season::STARTS_ON,
            ]);

            $this->scheduleSeason->handle($next);

            return $next;
        });
    }
}
