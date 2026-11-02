<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\Fixture;
use App\Models\LeagueRound;
use Illuminate\Support\Facades\DB;

class FinishRound
{
    /** True when every fixture of the matchday is played and the round was resolved by this call. */
    public function handle(LeagueRound $round): bool
    {
        return DB::transaction(function () use ($round): bool {
            LeagueRound::query()->whereKey($round->id)->lockForUpdate()->first();
            $round->refresh();

            if ($round->status !== LeagueRound::PLAYING && $round->status !== LeagueRound::OPEN) {
                return false;
            }

            $fixtures = $round->season->fixtures()
                ->where('youth', false)
                ->where('matchday', $round->matchday)
                ->get();

            if ($fixtures->contains(fn (Fixture $fixture): bool => ! $fixture->played)) {
                return false;
            }

            $date = $fixtures->first()?->scheduled_on;

            if ($date !== null) {
                $round->season->forceFill(['current_date' => $date])->save();
            }

            $round->markResolved();

            return true;
        });
    }
}
