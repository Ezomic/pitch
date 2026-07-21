<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\Fixture;
use App\Models\MatchSession;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Player::factory()->count(8)->create(['position' => Position::Defender]);
    Player::factory()->count(8)->create(['position' => Position::Midfielder]);
    Player::factory()->count(8)->create(['position' => Position::Forward]);
    Team::factory()->count(7)->create(['is_youth' => false]);
});

function readyFixture(User $user): Fixture
{
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);
    app(AdvanceWeek::class)->handle($season); // resolves rivals, leaves the user fixture due

    return $season->fixtures()
        ->where('played', false)
        ->where(fn ($q) => $q->whereNull('home_team_id')->orWhereNull('away_team_id'))
        ->firstOrFail();
}

it('offers the live match and blocks advancing while it is due', function () {
    $user = User::factory()->create();
    readyFixture($user);
    $dateAfterAdvance = $user->season->current_date->toDateString();

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertInertia(fn (Assert $page) => $page->has('liveFixture.url')->has('liveFixture.opponentName'));

    // Advancing again does nothing until the live match is played.
    $this->actingAs($user)->post(route('season.advance'));
    expect($user->season->refresh()->current_date->toDateString())->toBe($dateAfterAdvance);
});

it('renders the live match and opens a session', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);

    $this->actingAs($user)
        ->get(route('match.live.show', $fixture))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveMatch')
            ->has('opponentName')
            ->has('moments')
            ->has('finishUrl'),
        );

    expect(MatchSession::where('user_id', $user->id)->where('fixture_id', $fixture->id)->exists())->toBeTrue();
});

it('records the played-out score onto the fixture at full time', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);

    $this->actingAs($user)->get(route('match.live.show', $fixture));
    $session = MatchSession::where('fixture_id', $fixture->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('match.live.finish', $fixture))
        ->assertRedirect(route('season.show'));

    $fixture->refresh();
    [$expectedHome, $expectedAway] = $fixture->userIsHome()
        ? [$session->home_goals, $session->away_goals]
        : [$session->away_goals, $session->home_goals];

    expect($fixture->played)->toBeTrue()
        ->and($fixture->home_goals)->toBe($expectedHome)
        ->and($fixture->away_goals)->toBe($expectedAway)
        ->and(MatchSession::where('fixture_id', $fixture->id)->exists())->toBeFalse();
});

it('will not open a live match for a played or rival-only fixture', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $rivalFixture = $season->fixtures()
        ->whereNotNull('home_team_id')->whereNotNull('away_team_id')->firstOrFail();

    $this->actingAs($user)->get(route('match.live.show', $rivalFixture))->assertNotFound();
});
