<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
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

function dueFixture(User $user)
{
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);
    app(AdvanceWeek::class)->handle($season);

    return $season->fixtures()
        ->where('played', false)
        ->where(fn ($q) => $q->whereNull('home_team_id')->orWhereNull('away_team_id'))
        ->firstOrFail();
}

it('scouts the opponent tendencies and the user matchup', function () {
    $user = User::factory()->create();
    $fixture = dueFixture($user);

    $this->actingAs($user)
        ->get(route('season.scout', $fixture))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scout')
            ->has('opponentName')
            ->has('opponent.goalsPer90')
            ->has('opponent.goalsConcededPer90')
            ->has('matchup.goalsPer90')
            ->has('matchup.goalsConcededPer90'),
        );
});

it('exposes a scout link on the season page while a fixture is due', function () {
    $user = User::factory()->create();
    dueFixture($user);

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertInertia(fn (Assert $page) => $page->has('liveFixture.scoutUrl'));
});

it('will not scout a played or rival-only fixture', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $rivalFixture = $season->fixtures()
        ->whereNotNull('home_team_id')->whereNotNull('away_team_id')->firstOrFail();

    $this->actingAs($user)->get(route('season.scout', $rivalFixture))->assertNotFound();
});
