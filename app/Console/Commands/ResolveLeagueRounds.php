<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\League\CurrentRound;
use App\Actions\League\ResolveRound;
use App\Models\Career;
use App\Models\LeagueRound;
use Illuminate\Console\Command;

class ResolveLeagueRounds extends Command
{
    protected $signature = 'pitch:resolve-league-rounds';

    protected $description = 'Play any league matchday whose deadline has run out';

    /**
     * The lobby resolves a round whenever somebody opens it, which covers a
     * league people are playing. This covers the one they are not: a matchday
     * whose deadline passed while every manager was away still has to be
     * played, or the season stops the moment attention does.
     */
    public function handle(CurrentRound $currentRound, ResolveRound $resolve): int
    {
        $played = 0;

        foreach (Career::query()->where('type', Career::LEAGUE)->cursor() as $career) {
            $round = $currentRound->handle($career);

            if ($round instanceof LeagueRound && $resolve->handle($round)) {
                $played++;
                $this->line("Played matchday {$round->matchday} of {$career->name}.");
            }
        }

        $this->info("Played {$played} league ".($played === 1 ? 'round.' : 'rounds.'));

        return self::SUCCESS;
    }
}
