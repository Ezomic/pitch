<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Jobs\RunFixtureMatch;
use App\Models\Career;
use App\Models\CareerMembership;
use App\Models\Fixture;
use App\Models\LeagueOrder;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\FixtureResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Play a matchday once the league is done waiting for it.
 *
 * A round goes when every manager with a club has handed in a team sheet, or
 * when the deadline runs out, in which case whoever never showed up is played
 * by the club's own standing shape. That fallback is written down as an order
 * rather than applied silently, so a played round says what every side was
 * told to do.
 */
class ResolveRound
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
        private readonly FinishRound $finish = new FinishRound,
    ) {}

    /** True when the matchday was played, or for a nutmeg career handed to the runner, by this call. */
    public function handle(LeagueRound $round): bool
    {
        if (! $round->isOpen()) {
            return false;
        }

        $seats = $round->career->memberships()->whereNotNull('team_id')->orderBy('id')->get();

        // A league nobody runs a club in has not started, and a deadline should
        // not play its way through a season while the lobby is still filling up.
        if ($seats->isEmpty()) {
            return false;
        }

        $orders = $round->orders()->get()->keyBy('career_membership_id');

        $waiting = $seats->filter(fn (CareerMembership $seat): bool => $orders->get($seat->id)?->ready !== true);

        if ($waiting->isNotEmpty() && ! $round->deadlinePassed()) {
            return false;
        }

        DB::transaction(function () use ($round, $seats, $orders, $waiting): void {
            foreach ($waiting as $seat) {
                $orders->put($seat->id, $this->autoOrder($round, $seat));
            }

            /** @var array<int, LeagueOrder|null> $byTeam */
            $byTeam = [];
            foreach ($seats as $seat) {
                if ($seat->team_id !== null) {
                    $byTeam[$seat->team_id] = $orders->get($seat->id);
                }
            }

            if ($round->career->engine === Career::NUTMEG) {
                $this->dispatchMatchday($round);

                return;
            }

            $this->playMatchday($round, $byTeam);
        });

        return true;
    }

    private function dispatchMatchday(LeagueRound $round): void
    {
        $fixtures = $this->matchday($round);

        $round->markPlaying();

        if ($fixtures->isEmpty()) {
            $this->finish->handle($round);

            return;
        }

        foreach ($fixtures as $fixture) {
            RunFixtureMatch::dispatch($round->id, $fixture->id)->afterCommit();
        }
    }

    /**
     * @return Collection<int, Fixture>
     */
    private function matchday(LeagueRound $round): Collection
    {
        return $round->season->fixtures()
            ->where('youth', false)
            ->where('matchday', $round->matchday)
            ->where('played', false)
            ->get();
    }

    /**
     * @param  array<int, LeagueOrder|null>  $byTeam
     */
    private function playMatchday(LeagueRound $round, array $byTeam): void
    {
        $teams = Team::all()->keyBy('id');

        $fixtures = $this->matchday($round);

        foreach ($fixtures as $fixture) {
            $home = $teams->get($fixture->home_team_id);
            $away = $teams->get($fixture->away_team_id);

            if (! $home instanceof Team || ! $away instanceof Team) {
                throw new \RuntimeException("Missing team in fixture {$fixture->id}.");
            }

            $result = $this->resolver->resolve(
                $home->setup($byTeam[$home->id] ?? null),
                $away->setup($byTeam[$away->id] ?? null),
                $fixture->seed,
            );

            $fixture->update([
                'home_goals' => $result['home'],
                'away_goals' => $result['away'],
                'played' => true,
            ]);
        }

        $this->finish->handle($round);
    }

    private function autoOrder(LeagueRound $round, CareerMembership $seat): LeagueOrder
    {
        $team = $seat->team;
        $existing = $round->orderFor($seat);

        // A manager who named a side but never marked it ready still gets the side
        // they named; only a manager who said nothing falls back to the club.
        $attributes = [
            'formation' => $existing instanceof LeagueOrder
                ? $existing->formation
                : ($team instanceof Team ? $team->formation : Formation::default()->id),
            'mentality' => $existing instanceof LeagueOrder
                ? $existing->mentality
                : ($team instanceof Team ? $team->mentality : Mentality::Balanced->value),
            'ready' => true,
            'auto' => true,
        ];

        if ($existing instanceof LeagueOrder) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return $round->orders()->create([...$attributes, 'career_membership_id' => $seat->id]);
    }
}
