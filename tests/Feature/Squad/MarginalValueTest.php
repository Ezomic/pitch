<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\MarginalValue;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('reports a marginal goals and conceded delta per player and attribute', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $squad = app(EnsureSquad::class)->handle($user);

    $marginal = app(MarginalValue::class)->handle($squad);

    expect($marginal['delta'])->toBe(5)
        ->and($marginal['baseline']['goals'])->toBeFloat()
        ->and($marginal['rows'])->toHaveCount($squad->assignments()->count());

    $row = $marginal['rows'][0];
    expect($row)->toHaveKeys(['slot', 'name', 'attributes'])
        ->and($row['attributes'])->toHaveKeys(Player::ATTRIBUTES);

    foreach (Player::ATTRIBUTES as $attribute) {
        expect($row['attributes'][$attribute])->toHaveKeys(['goals', 'conceded']);
    }
});

it('is deterministic: the same squad yields the same marginals', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $squad = app(EnsureSquad::class)->handle($user);

    $a = app(MarginalValue::class)->handle($squad);
    $b = app(MarginalValue::class)->handle($squad);

    expect($a)->toEqual($b);
});

it('renders the what-if page', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();

    $this->actingAs($user)
        ->get(route('squad.what-if'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SquadWhatIf')
            ->has('marginal.rows')
            ->has('marginal.baseline.goals'),
        );
});
