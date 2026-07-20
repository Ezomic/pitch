<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\Standings;
use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\SimulateMatch;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function show(Request $request, EnsureSeason $ensureSeason, Standings $standings): Response
    {
        $season = $ensureSeason->handle($this->user($request))->load('fixtures');
        /** @var Collection<int, Team> $teams */
        $teams = Team::all()->keyBy('id');

        $nextUnplayed = $season->fixtures->firstWhere('played', false);
        $current = $nextUnplayed?->matchday;

        return Inertia::render('Season', [
            'standings' => $standings->handle($season),
            'matchdays' => $this->matchdays($season, $teams),
            'currentMatchday' => $current,
            'currentDate' => $season->current_date->toDateString(),
            'nextFixtureDate' => $nextUnplayed?->scheduled_on?->toDateString(),
            'nextFixture' => $this->nextFixture($season, $teams, $current),
            'complete' => $current === null,
        ]);
    }

    public function advance(Request $request, EnsureSeason $ensureSeason, AdvanceWeek $advanceWeek): RedirectResponse
    {
        $season = $ensureSeason->handle($this->user($request));

        $advanceWeek->handle($season);

        return to_route('season.show');
    }

    public function reset(Request $request, EnsureSeason $ensureSeason): RedirectResponse
    {
        $ensureSeason->handle($this->user($request))->delete();

        return to_route('season.show');
    }

    public function report(
        Request $request,
        Fixture $fixture,
        EnsureSeason $ensureSeason,
        EnsureSquad $ensureSquad,
        SimulateMatch $simulateMatch,
    ): Response {
        $user = $this->user($request);
        $season = $ensureSeason->handle($user);

        abort_unless($fixture->season_id === $season->id && $fixture->involvesUser() && $fixture->played, 404);

        $opponent = Team::findOrFail($fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id);
        $report = $simulateMatch->handle($ensureSquad->handle($user), $fixture->seed, $opponent->setup());

        return Inertia::render('Match', [
            'seed' => $fixture->seed,
            'report' => $report->toArray(),
            'opponentName' => $opponent->name,
            'hideReseed' => true,
        ]);
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    private function matchdays(Season $season, Collection $teams): array
    {
        $grouped = [];

        foreach ($season->fixtures->groupBy('matchday') as $matchday => $fixtures) {
            $grouped[] = [
                'matchday' => (int) $matchday,
                'fixtures' => $fixtures->map(fn (Fixture $fixture) => [
                    'id' => $fixture->id,
                    'homeName' => $this->sideName($fixture->home_team_id, $teams),
                    'awayName' => $this->sideName($fixture->away_team_id, $teams),
                    'homeGoals' => $fixture->home_goals,
                    'awayGoals' => $fixture->away_goals,
                    'played' => $fixture->played,
                    'isUser' => $fixture->involvesUser(),
                    'reportUrl' => $fixture->involvesUser() && $fixture->played
                        ? route('season.report', $fixture)
                        : null,
                ])->all(),
            ];
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return array<string, mixed>|null
     */
    private function nextFixture(Season $season, Collection $teams, ?int $current): ?array
    {
        if ($current === null) {
            return null;
        }

        $fixture = $season->fixtures
            ->where('matchday', $current)
            ->first(fn (Fixture $f) => $f->involvesUser());

        if ($fixture === null) {
            return null;
        }

        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;

        return [
            'opponentName' => $this->sideName($opponentId, $teams),
            'home' => $fixture->userIsHome(),
        ];
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function sideName(?int $teamId, Collection $teams): string
    {
        if ($teamId === null) {
            return 'Your squad';
        }

        return $teams->get($teamId)->name;
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
