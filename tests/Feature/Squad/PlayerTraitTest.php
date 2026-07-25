<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('applies a trait bonus to match attributes', function () {
    $clinical = Player::factory()->create(['finishing' => 60, 'fitness' => 100, 'form' => 0, 'trait' => 'clinical']);
    $plain = Player::factory()->create(['finishing' => 60, 'fitness' => 100, 'form' => 0, 'trait' => null]);

    expect($clinical->matchAttributes()->finishing)->toBe(70) // 60 + 10
        ->and($plain->matchAttributes()->finishing)->toBe(60); // untouched
});

it('leaves a traitless player unchanged', function () {
    $player = Player::factory()->create(['fitness' => 100, 'form' => 0, 'trait' => null]);

    expect($player->matchAttributes())->toEqual($player->attributes());
});

it('exposes the trait on the squad payload', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create(['trait' => 'pacey']);

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pool.0.trait'));
});
