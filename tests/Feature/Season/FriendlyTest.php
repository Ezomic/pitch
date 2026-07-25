<?php

declare(strict_types=1);

use App\Actions\Season\EnsureSeason;
use App\Actions\Season\PlayPreseason;
use App\Actions\Squad\EnsureSquad;
use App\Models\News;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seasonWithFriendlies(): array
{
    $user = User::factory()->create();
    Team::factory()->count(4)->create(['is_youth' => false]);
    Player::factory()->count(16)->create(['is_youth' => false]);
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);

    return [$user, $season];
}

it('schedules preseason friendlies at the start of a season', function () {
    [, $season] = seasonWithFriendlies();

    expect($season->friendlies()->count())->toBe(3)
        ->and($season->friendlies()->where('played', false)->count())->toBe(3);
});

it('plays out the friendlies and records their results without touching the league', function () {
    [$user, $season] = seasonWithFriendlies();

    app(PlayPreseason::class)->handle($season);

    expect($season->friendlies()->where('played', false)->count())->toBe(0)
        ->and($season->friendlies()->whereNotNull('user_goals')->count())->toBe(3)
        ->and(News::query()->where('user_id', $user->id)->where('category', News::RESULT)->count())->toBe(3);
});

it('leaves the first team fully fit and sharper after preseason', function () {
    [$user, $season] = seasonWithFriendlies();
    $squad = $user->squad()->first();
    $squad->assignments()->each(fn ($a) => Player::whereKey($a->player_id)->update(['fitness' => 60, 'form' => 0]));

    app(PlayPreseason::class)->handle($season);

    $fielded = Player::query()->whereIn('id', $squad->assignments()->pluck('player_id'))->get();
    expect($fielded->every(fn (Player $p) => $p->fitness === Player::FITNESS_MAX))->toBeTrue()
        ->and($fielded->every(fn (Player $p) => $p->form === 1))->toBeTrue();
});

it('does nothing once the friendlies have been played', function () {
    [, $season] = seasonWithFriendlies();
    app(PlayPreseason::class)->handle($season);
    $goalsBefore = $season->friendlies()->pluck('user_goals');

    app(PlayPreseason::class)->handle($season);

    expect($season->friendlies()->pluck('user_goals'))->toEqual($goalsBefore);
});

it('plays friendlies from the season page and shows their state', function () {
    [$user] = seasonWithFriendlies();

    $this->actingAs($user)
        ->post(route('season.friendlies'))
        ->assertRedirect(route('season.show'));

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Season')
            ->where('preseason.pending', false)
            ->has('preseason.matches', 3),
        );
});
