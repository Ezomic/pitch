<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Fixture;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Squad\FixtureResolver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayMatchday
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
    ) {}

    /**
     * Auto-resolve the rival fixtures on the next unplayed matchday. The user's
     * own fixture is left pending so it can be played live from the match viewer.
     */
    public function handle(Season $season): void
    {
        $matchday = $this->rivalFixtures($season)->where('played', false)->min('matchday');

        if ($matchday === null) {
            return;
        }

        $fixtures = $this->rivalFixtures($season)
            ->where('matchday', $matchday)
            ->where('played', false)
            ->get();

        /** @var Collection<int, Team> $teams */
        $teams = Team::all()->keyBy('id');

        DB::transaction(function () use ($fixtures, $teams): void {
            foreach ($fixtures as $fixture) {
                $home = $teams->get($fixture->home_team_id);
                $away = $teams->get($fixture->away_team_id);

                if (! $home instanceof Team || ! $away instanceof Team) {
                    throw new \RuntimeException("Missing team in fixture {$fixture->id}.");
                }

                $result = $this->resolver->resolve($home->setup(), $away->setup(), $fixture->seed);

                $fixture->update([
                    'home_goals' => $result['home'],
                    'away_goals' => $result['away'],
                    'played' => true,
                ]);
            }
        });
    }

    /**
     * Senior fixtures between two rival teams (neither side is the user).
     *
     * @return HasMany<Fixture, Season>
     */
    private function rivalFixtures(Season $season): HasMany
    {
        return $season->fixtures()
            ->where('youth', false)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id');
    }
}
