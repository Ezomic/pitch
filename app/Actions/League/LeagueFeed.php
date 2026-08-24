<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\Career;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * What the league has been talking about.
 *
 * Read off the fixtures rather than written into a news table as a round is
 * played: a result is already recorded once, and a second copy per member would
 * only be a copy that can fall out of step with the first.
 */
class LeagueFeed
{
    /** How far below its opponent a club has to be for beating them to be news. */
    private const int UPSET_GAP = 8;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Career $career, Season $season, int $matchdays = 3): array
    {
        /** @var array<int, string> $managers */
        $managers = [];
        foreach ($career->memberships()->with('user')->whereNotNull('team_id')->get() as $seat) {
            if ($seat->team_id !== null) {
                $managers[$seat->team_id] = $seat->user->name;
            }
        }

        $latest = (int) $season->fixtures()->where('youth', false)->where('played', true)->max('matchday');

        if ($latest === 0) {
            return [];
        }

        /** @var Collection<int, Team> $teams */
        $teams = Team::all()->keyBy('id');

        $played = $season->fixtures()->where('youth', false)->where('played', true)
            ->where('matchday', '>', max(0, $latest - $matchdays))
            ->orderByDesc('matchday')->orderBy('id')->get();

        return array_values($played->map(fn (Fixture $fixture): array => $this->item($fixture, $teams, $managers))->all());
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<int, string>  $managers
     * @return array<string, mixed>
     */
    private function item(Fixture $fixture, Collection $teams, array $managers): array
    {
        $home = $teams->get($fixture->home_team_id);
        $away = $teams->get($fixture->away_team_id);
        $homeGoals = (int) $fixture->home_goals;
        $awayGoals = (int) $fixture->away_goals;

        return [
            'fixtureId' => $fixture->id,
            'matchday' => $fixture->matchday,
            'home' => $home?->name,
            'away' => $away?->name,
            'homeGoals' => $homeGoals,
            'awayGoals' => $awayGoals,
            'homeManager' => $managers[$fixture->home_team_id] ?? null,
            'awayManager' => $managers[$fixture->away_team_id] ?? null,
            'duel' => isset($managers[$fixture->home_team_id], $managers[$fixture->away_team_id]),
            'upset' => $this->isUpset($home, $away, $homeGoals, $awayGoals),
        ];
    }

    /** A club well below its opponent taking the points. */
    private function isUpset(?Team $home, ?Team $away, int $homeGoals, int $awayGoals): bool
    {
        if (! $home instanceof Team || ! $away instanceof Team || $homeGoals === $awayGoals) {
            return false;
        }

        $winner = $homeGoals > $awayGoals ? $home : $away;
        $loser = $homeGoals > $awayGoals ? $away : $home;

        return LeagueTable::strength($loser) - LeagueTable::strength($winner) >= self::UPSET_GAP;
    }
}
