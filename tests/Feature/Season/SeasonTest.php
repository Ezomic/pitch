<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\PlayMatchday;
use App\Actions\Season\Standings;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Player::factory()->count(5)->create(['position' => Position::Defender]);
    Player::factory()->count(6)->create(['position' => Position::Midfielder]);
    Player::factory()->count(5)->create(['position' => Position::Forward]);
    Team::factory()->count(7)->create();
});

it('generates a full double round-robin schedule', function () {
    $season = app(EnsureSeason::class)->handle(User::factory()->create());

    expect($season->fixtures()->count())->toBe(56)
        ->and((int) $season->fixtures()->max('matchday'))->toBe(14);

    // Every participant (user = null, plus 7 teams) plays 14 fixtures.
    $counts = ['user' => 0];
    foreach (Team::pluck('id') as $id) {
        $counts[$id] = 0;
    }
    foreach ($season->fixtures as $fixture) {
        $counts[$fixture->home_team_id ?? 'user']++;
        $counts[$fixture->away_team_id ?? 'user']++;
        expect($fixture->home_team_id)->not->toBe($fixture->away_team_id);
    }

    expect(array_unique(array_values($counts)))->toBe([14]);
});

it('schedules the season on a weekly calendar', function () {
    $season = app(EnsureSeason::class)->handle(User::factory()->create());

    expect($season->starts_on->toDateString())->toBe(Season::STARTS_ON)
        ->and($season->current_date->toDateString())->toBe(Season::STARTS_ON);

    $first = $season->fixtures()->where('matchday', 1)->first();
    $second = $season->fixtures()->where('matchday', 2)->first();

    expect($first->scheduled_on->toDateString())->toBe($season->starts_on->copy()->addWeek()->toDateString())
        ->and($second->scheduled_on->toDateString())->toBe($first->scheduled_on->copy()->addWeek()->toDateString());
});

it('advances the calendar a week, plays the rivals and leaves the user fixture live', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $start = $season->current_date->copy();

    app(AdvanceWeek::class)->handle($season);
    $season->refresh();

    $matchday1 = $season->fixtures()->where('matchday', 1);
    $userFixture = (clone $matchday1)->where(fn ($q) => $q->whereNull('home_team_id')->orWhereNull('away_team_id'))->first();

    expect($season->current_date->toDateString())->toBe($start->addWeek()->toDateString())
        // Every rival-vs-rival fixture on matchday 1 is resolved...
        ->and((clone $matchday1)->whereNotNull('home_team_id')->whereNotNull('away_team_id')->where('played', false)->count())->toBe(0)
        // ...but the user's own fixture is left pending for the live match.
        ->and($userFixture->played)->toBeFalse();
});

it('renders the season page with standings and fixtures', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Season')
            ->has('standings', 8)
            ->has('matchdays', 14)
            ->where('currentMatchday', 1)
            ->where('currentDate', Season::STARTS_ON)
            ->has('nextFixtureDate')
            ->where('complete', false),
        );
});

it('advancing resolves the rival fixtures and advances the table', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('season.show'));

    $this->actingAs($user)->post(route('season.advance'))->assertRedirect(route('season.show'));

    $season = $user->season()->first();
    // Matchday 1 has 4 fixtures; three are rival-vs-rival, the user's is left for live play.
    expect($season->fixtures()->where('played', true)->count())->toBe(3);

    $table = app(Standings::class)->handle($season);
    expect(array_sum(array_column($table, 'played')))->toBe(6);
});

it('orders the standings by points then goal difference', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);

    foreach (range(1, 14) as $ignored) {
        app(PlayMatchday::class)->handle($season->fresh());
    }

    $table = app(Standings::class)->handle($season->fresh());

    for ($i = 1; $i < count($table); $i++) {
        $prev = $table[$i - 1];
        $current = $table[$i];
        $ordered = $prev['points'] > $current['points']
            || ($prev['points'] === $current['points'] && $prev['goalDifference'] >= $current['goalDifference']);

        expect($ordered)->toBeTrue();
    }
    expect(collect($table)->firstWhere('isUser', true))->not->toBeNull();
});

it('shows a report for a played user fixture', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('season.show'));
    $this->actingAs($user)->post(route('season.advance'));

    // The user's fixture is played live; the report is available afterwards.
    $season = $user->season()->first();
    $userFixture = $season->fixtures()->where('played', false)->get()
        ->first(fn ($f) => $f->involvesUser());
    // Played out in the positional engine at /play, a slice at a time.
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    for ($i = 0; $i < 40; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])
            ->json();

        if ($body['finished']) {
            break;
        }
    }

    $this->actingAs($user)
        ->get(route('season.report', $userFixture->refresh()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Match')->has('report'));
});

it('does not show a report for a rival-only fixture', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('season.show'));
    $this->actingAs($user)->post(route('season.advance'));

    $season = $user->season()->first();
    $rivalFixture = $season->fixtures()->where('played', true)->get()
        ->first(fn ($f) => ! $f->involvesUser());

    $this->actingAs($user)
        ->get(route('season.report', $rivalFixture))
        ->assertNotFound();
});

it('requires authentication', function () {
    $this->get(route('season.show'))->assertRedirect(route('login'));
});
