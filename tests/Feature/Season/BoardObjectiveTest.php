<?php

declare(strict_types=1);

use App\Actions\Season\EnsureSeason;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(7)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);
});

it('states a top-half board objective with no verdict mid-season', function () {
    $user = User::factory()->create();
    app(EnsureSeason::class)->handle($user);

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('objective.teams', 8)
            ->where('objective.target', 4) // top half of 8
            ->where('objective.met', null),
        );
});

it('delivers a verdict once the season is complete', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $season->fixtures()->update(['played' => true]);

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('objective.met', fn ($met) => is_bool($met))
            ->has('objective.position'),
        );
});
