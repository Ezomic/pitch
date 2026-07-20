<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\Squad\EnsureSquad;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayMatchday
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
        private readonly EnsureSquad $ensureSquad = new EnsureSquad,
    ) {}

    /**
     * Resolve every fixture on the next unplayed matchday. The user's fixture is
     * played with their current squad; the rest are auto-simulated.
     */
    public function handle(Season $season): void
    {
        $matchday = $season->fixtures()->where('played', false)->min('matchday');

        if ($matchday === null) {
            return;
        }

        $fixtures = $season->fixtures()
            ->where('matchday', $matchday)
            ->where('played', false)
            ->get();

        /** @var Collection<int, Team> $teams */
        $teams = Team::all()->keyBy('id');
        $userSetup = $this->ensureSquad->handle($season->user)->setup();

        DB::transaction(function () use ($fixtures, $teams, $userSetup): void {
            foreach ($fixtures as $fixture) {
                $result = $this->resolver->resolve(
                    $this->sideSetup($fixture->home_team_id, $teams, $userSetup),
                    $this->sideSetup($fixture->away_team_id, $teams, $userSetup),
                    $fixture->seed,
                );

                $fixture->update([
                    'home_goals' => $result['home'],
                    'away_goals' => $result['away'],
                    'played' => true,
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function sideSetup(?int $teamId, Collection $teams, TeamSetup $userSetup): TeamSetup
    {
        if ($teamId === null) {
            return $userSetup;
        }

        $team = $teams->get($teamId);

        if (! $team instanceof Team) {
            throw new \RuntimeException("Missing team {$teamId} in fixture.");
        }

        return $team->setup();
    }
}
