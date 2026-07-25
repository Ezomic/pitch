<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\RolloverSeason;
use App\Actions\Season\ScoutOpponent;
use App\Actions\Season\Standings;
use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\SimulateMatch;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\Squad;
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
        $season = $ensureSeason->handle($this->user($request))
            ->load(['fixtures' => fn ($query) => $query->where('youth', false)]);
        /** @var Collection<int, Team> $teams */
        $teams = Team::all()->keyBy('id');

        $nextUnplayed = $season->fixtures->firstWhere('played', false);
        $current = $nextUnplayed?->matchday;
        $due = $this->dueUserFixture($season);

        $table = $standings->handle($season);

        return Inertia::render('Season', [
            'seasonNumber' => $season->number,
            'division' => $season->division,
            'promotes' => $season->division > Squad::TOP_DIVISION,
            'relegates' => $season->division < Squad::BOTTOM_DIVISION,
            'history' => $this->history($this->user($request), $standings),
            'objective' => $this->objective($table, $current === null),
            'standings' => $table,
            'matchdays' => $this->matchdays($season, $teams),
            'currentMatchday' => $current,
            'currentDate' => $season->current_date->toDateString(),
            'nextFixtureDate' => $nextUnplayed?->scheduled_on?->toDateString(),
            'nextFixture' => $this->nextFixture($season, $teams, $current),
            'liveFixture' => $due === null ? null : [
                'opponentName' => $this->sideName($due->userIsHome() ? $due->away_team_id : $due->home_team_id, $teams),
                'home' => $due->userIsHome(),
                'url' => route('match.live.show', $due),
                'scoutUrl' => route('season.scout', $due),
            ],
            'complete' => $current === null,
        ]);
    }

    public function advance(Request $request, EnsureSeason $ensureSeason, AdvanceWeek $advanceWeek): RedirectResponse
    {
        $season = $ensureSeason->handle($this->user($request));

        // The user must play their own fixture live before the season rolls on.
        if ($this->dueUserFixture($season) === null) {
            $advanceWeek->handle($season);
        }

        return to_route('season.show');
    }

    public function scout(
        Request $request,
        Fixture $fixture,
        EnsureSeason $ensureSeason,
        EnsureSquad $ensureSquad,
        ScoutOpponent $scoutOpponent,
    ): Response {
        $user = $this->user($request);
        $season = $ensureSeason->handle($user);

        abort_unless($fixture->season_id === $season->id && $fixture->involvesUser() && ! $fixture->played, 404);

        $opponent = Team::findOrFail($fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id);
        $scout = $scoutOpponent->handle($ensureSquad->handle($user), $opponent);

        return Inertia::render('Scout', [
            'opponentName' => $opponent->name,
            'style' => $opponent->style,
            'home' => $fixture->userIsHome(),
            'opponent' => $scout['opponent'],
            'matchup' => $scout['matchup'],
        ]);
    }

    private function dueUserFixture(Season $season): ?Fixture
    {
        return $season->fixtures()
            ->where('youth', false)
            ->where('played', false)
            ->where(fn ($query) => $query->whereNull('home_team_id')->orWhereNull('away_team_id'))
            ->whereDate('scheduled_on', '<=', $season->current_date)
            ->orderBy('matchday')
            ->first();
    }

    public function reset(Request $request, EnsureSeason $ensureSeason): RedirectResponse
    {
        $ensureSeason->handle($this->user($request))->delete();

        return to_route('season.show');
    }

    public function rollover(Request $request, EnsureSeason $ensureSeason, RolloverSeason $rolloverSeason): RedirectResponse
    {
        $season = $ensureSeason->handle($this->user($request));

        // Only roll over once the campaign is done: no unplayed senior fixtures left.
        if ($season->fixtures()->where('youth', false)->where('played', false)->doesntExist()) {
            $rolloverSeason->handle($season);
        }

        return to_route('season.show');
    }

    /**
     * The board's expectation for the campaign: a top-half finish. Returns the
     * target position, the user's current standing, and (once the season is done)
     * whether it was met.
     *
     * @param  list<array<string, mixed>>  $table
     * @return array{target: int, position: int, teams: int, met: bool|null}
     */
    private function objective(array $table, bool $complete): array
    {
        $teams = count($table);
        $target = max(1, intdiv($teams, 2));

        $position = $teams;
        foreach ($table as $index => $row) {
            if ($row['isUser'] === true) {
                $position = $index + 1;
                break;
            }
        }

        return [
            'target' => $target,
            'position' => $position,
            'teams' => $teams,
            'met' => $complete ? $position <= $target : null,
        ];
    }

    /**
     * @return list<array{number: int, position: int, points: int, teams: int}>
     */
    private function history(User $user, Standings $standings): array
    {
        $history = [];

        foreach ($user->seasons()->whereNotNull('completed_at')->orderByDesc('number')->get() as $season) {
            $table = $standings->handle($season);
            $position = 0;
            foreach ($table as $index => $row) {
                if ($row['isUser'] === true) {
                    $position = $index + 1;
                    break;
                }
            }

            $userRow = collect($table)->firstWhere('isUser', true);

            $history[] = [
                'number' => $season->number,
                'position' => $position,
                'points' => is_array($userRow) ? (int) $userRow['points'] : 0,
                'teams' => count($table),
            ];
        }

        return $history;
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
                    'isDerby' => $fixture->involvesUser()
                        && (bool) $teams->get($fixture->home_team_id ?? $fixture->away_team_id)->is_derby,
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
