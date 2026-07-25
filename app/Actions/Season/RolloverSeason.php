<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\Squad\EnsureSquad;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

/**
 * Close the finished campaign and open the next one. The completed season is kept
 * (stamped completed_at) for history; a fresh season with the next number gets a
 * new fixture list. The squad and academy carry over, a year older, with a new
 * crop of youth arriving, and the club's final position may move it up or down a
 * division for the campaign ahead.
 */
class RolloverSeason
{
    public function __construct(
        private readonly ScheduleSeason $scheduleSeason = new ScheduleSeason,
        private readonly AgePlayers $agePlayers = new AgePlayers,
        private readonly GenerateYouthIntake $generateYouthIntake = new GenerateYouthIntake,
        private readonly PromoteRelegate $promoteRelegate = new PromoteRelegate,
        private readonly EnsureSquad $ensureSquad = new EnsureSquad,
        private readonly DrawCup $drawCup = new DrawCup,
    ) {}

    public function handle(Season $current): Season
    {
        return DB::transaction(function () use ($current): Season {
            $current->forceFill(['completed_at' => now()])->save();

            $squad = $this->ensureSquad->handle($current->user);
            $this->promoteRelegate->handle($squad, $current);

            $this->agePlayers->handle($current->user);
            $this->generateYouthIntake->handle($current->user);

            $next = Season::create([
                'user_id' => $current->user_id,
                'number' => $current->number + 1,
                'division' => $squad->division,
                'starts_on' => Season::STARTS_ON,
                'current_date' => Season::STARTS_ON,
            ]);

            $this->scheduleSeason->handle($next);
            $this->drawCup->handle($next);

            return $next;
        });
    }
}
