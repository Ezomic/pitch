<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\Career;
use App\Models\LeagueRound;
use App\Models\Season;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;

/**
 * The matchday a league is currently waiting on, opened if it is not already.
 *
 * The deadline is stamped when the round opens rather than derived on the fly:
 * changing the league's cadence must not move a clock managers are already
 * playing against.
 */
class CurrentRound
{
    public function __construct(
        private readonly EnsureLeagueSeason $ensureSeason = new EnsureLeagueSeason,
    ) {}

    public function handle(Career $career): ?LeagueRound
    {
        $season = $this->ensureSeason->handle($career);
        $matchday = $this->nextMatchday($season);

        if ($matchday === null) {
            return null; // the season is played out
        }

        $existing = $career->rounds()->where('matchday', $matchday)->first();

        if ($existing instanceof LeagueRound) {
            return $existing;
        }

        try {
            return $career->rounds()->create([
                'season_id' => $season->id,
                'matchday' => $matchday,
                'status' => LeagueRound::OPEN,
                'deadline_at' => Date::now()->addHours($career->round_hours),
            ]);
        } catch (QueryException) {
            // Two managers opened the lobby at once; one of them lost the race.
            return $career->rounds()->where('matchday', $matchday)->first();
        }
    }

    private function nextMatchday(Season $season): ?int
    {
        $matchday = $season->fixtures()->where('youth', false)->where('played', false)->min('matchday');

        return $matchday === null ? null : (int) $matchday;
    }
}
