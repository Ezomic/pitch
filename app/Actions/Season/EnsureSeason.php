<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnsureSeason
{
    public function __construct(
        private readonly ScheduleSeason $scheduleSeason = new ScheduleSeason,
        private readonly DrawCup $drawCup = new DrawCup,
        private readonly SchedulePreseason $schedulePreseason = new SchedulePreseason,
    ) {}

    /**
     * Return the user's active season, creating one with a full double
     * round-robin fixture list on first use. The user (represented by a null team
     * id) plays every seeded rival home and away.
     */
    public function handle(User $user): Season
    {
        $existing = $user->season()->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user): Season {
            $squad = $user->squad()->first();

            $season = Season::create([
                'user_id' => $user->id,
                'number' => 1,
                'division' => $squad !== null ? $squad->division : Squad::DEFAULT_DIVISION,
                'starts_on' => Season::STARTS_ON,
                'current_date' => Season::STARTS_ON,
            ]);

            $this->scheduleSeason->handle($season);
            $this->drawCup->handle($season);
            $this->schedulePreseason->handle($season);

            return $season;
        });
    }
}
