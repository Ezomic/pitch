<?php

declare(strict_types=1);

use App\Actions\Squad\AssignKeeper;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('assigns a goalkeeper to the squad', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);
    $keeper = Player::factory()->create(['position' => Position::Goalkeeper, 'handling' => 80, 'is_youth' => false]);

    app(AssignKeeper::class)->handle($squad, $keeper);

    expect($squad->refresh()->goalkeeper_id)->toBe($keeper->id)
        ->and($squad->goalkeeping())->toBeGreaterThan(1);
});

it('refuses to assign an outfielder as the keeper', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);
    $outfielder = Player::factory()->create(['position' => Position::Midfielder, 'is_youth' => false]);

    expect(fn () => app(AssignKeeper::class)->handle($squad, $outfielder))
        ->toThrow(ValidationException::class);
    expect($squad->refresh()->goalkeeper_id)->toBeNull();
});

it('falls back to a reserve keeping level when no keeper is assigned', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);

    expect($squad->goalkeeping())->toBe(Squad::DEFAULT_KEEPING);
});

it('gives a fresh squad its best available keeper', function () {
    $user = User::factory()->create();
    Player::factory()->create(['position' => Position::Goalkeeper, 'handling' => 55, 'is_youth' => false]);
    $best = Player::factory()->create(['position' => Position::Goalkeeper, 'handling' => 85, 'is_youth' => false]);

    $squad = app(EnsureSquad::class)->handle($user);

    expect($squad->goalkeeper_id)->toBe($best->id);
});

it('renders the goalkeeper picker on the squad page', function () {
    $user = User::factory()->create();
    Player::factory()->create(['position' => Position::Goalkeeper, 'handling' => 70, 'is_youth' => false]);
    Player::factory()->count(14)->create(['is_youth' => false]);

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Squad')
            ->has('keepers')
            ->has('keepers.0.handling')
            ->has('squad.goalkeeperId'),
        );
});
