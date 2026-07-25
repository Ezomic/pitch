<?php

declare(strict_types=1);

use App\Actions\Season\EnsureSeason;
use App\Actions\Season\RolloverSeason;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(7)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);
});

it('opens the first season as number one', function () {
    $season = app(EnsureSeason::class)->handle(User::factory()->create());

    expect($season->number)->toBe(1)
        ->and($season->completed_at)->toBeNull()
        ->and($season->fixtures()->where('youth', false)->count())->toBeGreaterThan(0);
});

it('rolls over into the next numbered season and keeps the old one for history', function () {
    $user = User::factory()->create();
    $first = app(EnsureSeason::class)->handle($user);

    $second = app(RolloverSeason::class)->handle($first);

    expect($second->number)->toBe(2)
        ->and($first->refresh()->completed_at)->not->toBeNull()
        ->and($user->season()->first()->id)->toBe($second->id) // active season is the new one
        ->and($user->seasons()->count())->toBe(2)
        ->and($second->fixtures()->where('youth', false)->count())->toBe($first->fixtures()->where('youth', false)->count());
});

it('only rolls over once the campaign is complete', function () {
    $user = User::factory()->create();
    app(EnsureSeason::class)->handle($user);

    // Fixtures are unplayed, so the rollover route is a no-op.
    $this->actingAs($user)->post(route('season.rollover'));

    expect($user->seasons()->count())->toBe(1)
        ->and($user->season()->first()->number)->toBe(1);
});

it('shows the season number and past-season history on the season page', function () {
    $user = User::factory()->create();
    $first = app(EnsureSeason::class)->handle($user);
    app(RolloverSeason::class)->handle($first);

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seasonNumber', 2)
            ->has('division')
            ->has('promotes')
            ->has('relegates')
            ->has('history', 1)
            ->where('history.0.number', 1),
        );
});
