<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\Youth\BuildYouthTeam;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Play any youth-league fixtures now due. The user's academy XI takes on the
 * scheduled youth side; prospects who feature get an extra week of development
 * from the match minutes.
 */
class PlayYouthFixtures
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
        private readonly BuildYouthTeam $buildYouthTeam = new BuildYouthTeam,
        private readonly DevelopPlayers $developPlayers = new DevelopPlayers,
    ) {}

    public function handle(Season $season): void
    {
        $current = CarbonImmutable::parse($season->current_date);

        $fixtures = $season->fixtures()
            ->where('youth', true)
            ->where('played', false)
            ->whereDate('scheduled_on', '<=', $current)
            ->get();

        if ($fixtures->isEmpty()) {
            return;
        }

        /** @var Collection<int, Team> $teams */
        $teams = Team::query()->where('is_youth', true)->get()->keyBy('id');
        $userSetup = $this->buildYouthTeam->forUser($season->user);

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

        $this->developPlayers->handle($this->buildYouthTeam->featured($season->user));
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
            throw new \RuntimeException("Missing youth team {$teamId} in fixture.");
        }

        return $team->setup();
    }
}
