<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Actions\Season\ScheduleSeason;
use App\Models\Career;
use App\Models\Season;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;

/**
 * The one season a league is played in.
 *
 * A solo save gives every manager a season of their own, because there is only
 * ever one of them. A league cannot work that way: the whole point is that the
 * managers are in the same table, so the career holds a single season and every
 * member reads the same fixture list.
 */
class EnsureLeagueSeason
{
    public function __construct(
        private readonly ScheduleSeason $scheduleSeason = new ScheduleSeason,
    ) {}

    public function handle(Career $career): Season
    {
        $existing = $career->seasons()->whereNull('completed_at')->orderBy('id')->first();

        if ($existing instanceof Season) {
            return $existing;
        }

        return DB::transaction(function () use ($career): Season {
            $season = Season::create([
                'user_id' => $career->user_id,
                'career_id' => $career->id,
                'number' => 1,
                'division' => Squad::DEFAULT_DIVISION,
                'starts_on' => Season::STARTS_ON,
                'current_date' => Season::STARTS_ON,
            ]);

            $this->scheduleSeason->handleLeague($season);

            return $season;
        });
    }
}
