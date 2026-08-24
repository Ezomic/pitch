<?php

declare(strict_types=1);

use App\Actions\Career\ClaimClub;
use App\Actions\Career\CreateCareer;
use App\Actions\League\CurrentRound;
use App\Actions\League\EnsureLeagueSeason;
use App\Actions\League\LeagueFeed;
use App\Actions\League\LeagueTable;
use App\Actions\League\ResolveRound;
use App\Actions\League\SubmitOrder;
use App\Models\Career;
use App\Models\Fixture;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(4)->create(['is_youth' => false]);
});

/** A one-manager league with a club claimed and its first matchday played. */
function playedLeague(): array
{
    $owner = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Sunday League', Career::LEAGUE);
    $club = Team::query()->where('is_youth', false)->orderBy('id')->firstOrFail();
    app(ClaimClub::class)->handle($career->seatFor($owner), $club);

    $round = app(CurrentRound::class)->handle($career);
    app(SubmitOrder::class)->handle($round, $career->seatFor($owner), '442', 'balanced', true);
    app(ResolveRound::class)->handle($round);

    return [$career, $owner, $club];
}

it('gives every club a row, and names the manager running one', function () {
    [$career, $owner, $club] = playedLeague();

    $table = app(LeagueTable::class)->handle($career, app(EnsureLeagueSeason::class)->handle($career));

    expect($table)->toHaveCount(4);

    $yours = collect($table)->firstWhere('teamId', $club->id);
    $others = collect($table)->where('teamId', '!=', $club->id);

    expect($yours['manager'])->toBe($owner->name)
        // Nobody runs the rest, so nothing claims to.
        ->and($others->pluck('manager')->filter())->toBeEmpty();
});

it('adds up a played matchday the way a table should', function () {
    [$career] = playedLeague();
    $season = app(EnsureLeagueSeason::class)->handle($career);

    $table = app(LeagueTable::class)->handle($career, $season);
    $played = $season->fixtures()->where('youth', false)->where('matchday', 1)->get();

    expect(collect($table)->sum('played'))->toBe($played->count() * 2)
        ->and(collect($table)->sum('goalsFor'))->toBe(collect($table)->sum('goalsAgainst'))
        ->and(collect($table)->sum('points'))->toBe(
            $played->sum(fn (Fixture $f): int => $f->home_goals === $f->away_goals ? 2 : 3)
        );
});

it('sorts on points, then goal difference, then goals scored', function () {
    [$career] = playedLeague();
    $table = app(LeagueTable::class)->handle($career, app(EnsureLeagueSeason::class)->handle($career));

    $keys = array_map(
        fn (array $row): array => [-$row['points'], -$row['goalDifference'], -$row['goalsFor']],
        $table,
    );
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted);
});

it('reports the matchday just played, and marks the ones a manager was in', function () {
    [$career, $owner, $club] = playedLeague();

    $feed = app(LeagueFeed::class)->handle($career, app(EnsureLeagueSeason::class)->handle($career));

    expect($feed)->not->toBeEmpty()
        ->and(collect($feed)->every(fn (array $i): bool => $i['matchday'] === 1))->toBeTrue();

    $yours = collect($feed)->first(
        fn (array $i): bool => $i['homeManager'] === $owner->name || $i['awayManager'] === $owner->name
    );

    expect($yours)->not->toBeNull()
        // One manager in a league means no duels yet.
        ->and(collect($feed)->pluck('duel')->unique()->all())->toBe([false]);
});

it('has nothing to report before a ball is kicked', function () {
    $owner = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Fresh', Career::LEAGUE);

    expect(app(LeagueFeed::class)->handle($career, app(EnsureLeagueSeason::class)->handle($career)))->toBe([]);
});

it('calls it an upset when a much weaker club takes the points', function () {
    $owner = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Sunday League', Career::LEAGUE);
    $season = app(EnsureLeagueSeason::class)->handle($career);

    $weak = Team::factory()->create(['is_youth' => false, 'vision' => 30, 'passing' => 30, 'dribbling' => 30, 'finishing' => 30, 'tackling' => 30, 'pace' => 30]);
    $strong = Team::factory()->create(['is_youth' => false, 'vision' => 80, 'passing' => 80, 'dribbling' => 80, 'finishing' => 80, 'tackling' => 80, 'pace' => 80]);

    $fixture = $season->fixtures()->create([
        'matchday' => 1, 'youth' => false, 'scheduled_on' => $season->starts_on,
        'home_team_id' => $weak->id, 'away_team_id' => $strong->id,
        'home_goals' => 2, 'away_goals' => 0, 'played' => true, 'seed' => 1,
    ]);

    $feed = app(LeagueFeed::class)->handle($career, $season);
    $item = collect($feed)->firstWhere('fixtureId', $fixture->id);

    expect($item['upset'])->toBeTrue();

    // Turn it round and the same scoreline is just the stronger side winning.
    $fixture->update(['home_goals' => 0, 'away_goals' => 2]);

    expect(collect(app(LeagueFeed::class)->handle($career, $season))->firstWhere('fixtureId', $fixture->id)['upset'])
        ->toBeFalse();
});

it('puts the table and the results on the league page', function () {
    [$career, $owner, $club] = playedLeague();

    $this->actingAs($owner)->get(route('league.show', $career))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('table', 4)
            ->has('feed')
            ->where('table.0.teamId', fn ($id) => is_int($id)));

    expect($club->fresh())->not->toBeNull();
});
