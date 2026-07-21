<?php

declare(strict_types=1);

use App\Actions\Squad\CompareSetups;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('evaluates two setups of the same squad', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $squad = app(EnsureSquad::class)->handle($user);

    $profiles = app(CompareSetups::class)->handle($squad, '442', 'defensive', '343', 'attacking');

    expect($profiles['a'])->toHaveKey('goalsPer90')
        ->and($profiles['b'])->toHaveKey('goalsConcededPer90');

    // An attacking 3-4-3 creates more, and concedes at least as much, as a
    // defensive 4-4-2 (the strict attacking-concedes-more property is proven in
    // TacticsTest with controlled attributes; here the random pool can tie).
    expect($profiles['b']['chancesPer90'])->toBeGreaterThan($profiles['a']['chancesPer90'])
        ->and($profiles['b']['chancesConcededPer90'])->toBeGreaterThanOrEqual($profiles['a']['chancesConcededPer90']);
});

it('renders the compare page and defaults setup A to the current squad', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $squad = app(EnsureSquad::class)->handle($user);

    $this->actingAs($user)
        ->get(route('squad.compare'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SquadCompare')
            ->where('setup.formationA', $squad->formation)
            ->has('profiles.a.goalsPer90')
            ->has('profiles.b.goalsPer90'),
        );
});

it('rejects an invalid formation', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();

    $this->actingAs($user)
        ->get(route('squad.compare', ['formationA' => 'nonsense']))
        ->assertSessionHasErrors('formationA');
});
