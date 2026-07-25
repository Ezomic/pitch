<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Team;

/**
 * Line up a short run of preseason friendlies against rival sides. They exist
 * only to build match fitness before the league starts, and never touch the
 * standings.
 */
class SchedulePreseason
{
    private const int FRIENDLIES = 3;

    public function handle(Season $season): void
    {
        if ($season->friendlies()->exists()) {
            return;
        }

        $rivals = Team::query()->where('is_youth', false)->orderBy('id')->pluck('id');

        if ($rivals->isEmpty()) {
            return;
        }

        for ($slot = 0; $slot < self::FRIENDLIES; $slot++) {
            $season->friendlies()->create([
                'slot' => $slot,
                'opponent_team_id' => $rivals[$slot % $rivals->count()],
                'home' => $slot % 2 === 0,
                'seed' => $season->id * 1000 + 600 + $slot,
            ]);
        }
    }
}
