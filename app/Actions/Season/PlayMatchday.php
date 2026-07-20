<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\Squad\EnsureSquad;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Domain\Attributes;
use App\Sim\Squad\FixtureResolver;
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
        $userBySlot = $this->ensureSquad->handle($season->user)->attributesBySlot();

        DB::transaction(function () use ($fixtures, $teams, $userBySlot): void {
            foreach ($fixtures as $fixture) {
                $result = $this->resolver->resolve(
                    $this->sideBySlot($fixture->home_team_id, $teams, $userBySlot),
                    $this->sideBySlot($fixture->away_team_id, $teams, $userBySlot),
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
     * @param  array<int, Attributes>  $userBySlot
     * @return array<int, Attributes>
     */
    private function sideBySlot(?int $teamId, Collection $teams, array $userBySlot): array
    {
        if ($teamId === null) {
            return $userBySlot;
        }

        $team = $teams->get($teamId);

        if (! $team instanceof Team) {
            throw new \RuntimeException("Missing team {$teamId} in fixture.");
        }

        return $team->bySlot();
    }
}
