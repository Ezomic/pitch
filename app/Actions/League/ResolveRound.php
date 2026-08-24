<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\CareerMembership;
use App\Models\Fixture;
use App\Models\LeagueOrder;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;
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
    ) {}

    /** True when the matchday was played by this call. */
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

            $this->playMatchday($round, $byTeam);

            $round->markResolved();
        });

        return true;
    }

    /**
     * @param  array<int, LeagueOrder|null>  $byTeam
     */
    private function playMatchday(LeagueRound $round, array $byTeam): void
    {
        $teams = Team::all()->keyBy('id');

        $fixtures = $round->season->fixtures()
            ->where('youth', false)
            ->where('matchday', $round->matchday)
            ->where('played', false)
            ->get();

        foreach ($fixtures as $fixture) {
            $home = $teams->get($fixture->home_team_id);
            $away = $teams->get($fixture->away_team_id);

            if (! $home instanceof Team || ! $away instanceof Team) {
                throw new \RuntimeException("Missing team in fixture {$fixture->id}.");
            }

            $result = $this->resolver->resolve(
                $this->setup($home, $byTeam[$home->id] ?? null),
                $this->setup($away, $byTeam[$away->id] ?? null),
                $fixture->seed,
            );

            $fixture->update([
                'home_goals' => $result['home'],
                'away_goals' => $result['away'],
                'played' => true,
            ]);
        }

        $this->advanceSeasonDate($round, $fixtures->first());
    }

    /** The club as it was told to play, or as it always plays when nobody runs it. */
    private function setup(Team $team, ?LeagueOrder $order): TeamSetup
    {
        if ($order === null) {
            return $team->setup();
        }

        return new TeamSetup(
            $team->bySlot(),
            Formation::fromId($order->formation),
            Mentality::fromId($order->mentality),
            $team->keeping,
            $team->set_pieces,
        );
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

    private function advanceSeasonDate(LeagueRound $round, ?Fixture $fixture): void
    {
        if (! $fixture instanceof Fixture || $fixture->scheduled_on === null) {
            return;
        }

        $round->season->forceFill(['current_date' => $fixture->scheduled_on])->save();
    }
}
