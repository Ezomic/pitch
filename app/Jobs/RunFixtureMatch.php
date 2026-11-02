<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\League\FinishRound;
use App\Models\Fixture;
use App\Models\LeagueOrder;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Sim\Runner\MatchRunner;
use App\Sim\Runner\MatchRunnerException;
use App\Sim\Runner\RunnerInput;
use App\Sim\Squad\TeamSetup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunFixtureMatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $roundId,
        public readonly int $fixtureId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        /** @var list<int> $backoff */
        $backoff = config('pitch.runner.backoff');

        return $backoff;
    }

    public function handle(MatchRunner $runner, FinishRound $finish): void
    {
        $round = LeagueRound::query()->findOrFail($this->roundId);
        $fixture = Fixture::query()->findOrFail($this->fixtureId);

        if ($round->status !== LeagueRound::PLAYING || $fixture->played) {
            return;
        }

        $result = $runner->run(new RunnerInput(
            $this->setup($round, $fixture->home_team_id),
            $this->setup($round, $fixture->away_team_id),
            $fixture->seed,
        ));

        $fixture->forceFill([
            'home_goals' => $result->homeGoals,
            'away_goals' => $result->awayGoals,
            'played' => true,
            'runner_result' => [
                'home_goals' => $result->homeGoals,
                'away_goals' => $result->awayGoals,
                'stats' => $result->stats,
            ],
            'runner_events' => $result->events,
            'runner_version' => $result->version,
        ])->save();

        $finish->handle($round);
    }

    public function failed(Throwable $exception): void
    {
        LeagueRound::query()->find($this->roundId)?->markFailed();
    }

    private function setup(LeagueRound $round, ?int $teamId): TeamSetup
    {
        $team = Team::query()->find($teamId);

        if (! $team instanceof Team) {
            throw new MatchRunnerException("Missing team {$teamId} in fixture {$this->fixtureId}.");
        }

        $order = $round->orders()
            ->whereHas('membership', fn ($query) => $query->where('team_id', $team->id))
            ->first();

        return $team->setup($order instanceof LeagueOrder ? $order : null);
    }
}
