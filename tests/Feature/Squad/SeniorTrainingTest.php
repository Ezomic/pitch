<?php

declare(strict_types=1);

use App\Actions\Season\TrainSeniors;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function trainingSeason(User $user): Season
{
    return Season::create([
        'user_id' => $user->id,
        'number' => 1,
        'starts_on' => Season::STARTS_ON,
        'current_date' => Season::STARTS_ON,
    ]);
}

it('improves a trained attribute at a fitness cost', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false,
        'training_focus' => 'finishing', 'finishing' => 60, 'potential' => 90, 'fitness' => 100,
    ]);

    app(TrainSeniors::class)->handle(trainingSeason($user));

    $player->refresh();
    expect($player->finishing)->toBe(60 + Player::SENIOR_TRAIN_STEP)
        ->and($player->fitness)->toBe(100 - Player::SENIOR_TRAIN_FITNESS_COST);
});

it('does not train a senior without a focus', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'training_focus' => null, 'finishing' => 60, 'fitness' => 100]);

    app(TrainSeniors::class)->handle(trainingSeason($user));

    expect($player->refresh()->finishing)->toBe(60)
        ->and($player->fitness)->toBe(100);
});

it('skips training when a player is too tired', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false,
        'training_focus' => 'pace', 'pace' => 50, 'potential' => 90,
        'fitness' => Player::SENIOR_TRAIN_MIN_FITNESS - 1,
    ]);

    app(TrainSeniors::class)->handle(trainingSeason($user));

    expect($player->refresh()->pace)->toBe(50);
});

it('will not train an attribute past the player ceiling', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false,
        'training_focus' => 'tackling', 'tackling' => 70, 'potential' => 70, 'fitness' => 100,
    ]);

    app(TrainSeniors::class)->handle(trainingSeason($user));

    expect($player->refresh()->tackling)->toBe(70)
        ->and($player->fitness)->toBe(100);
});

it('does not train youth prospects', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create(['training_focus' => 'passing', 'passing' => 40, 'potential' => 90, 'fitness' => 100]);

    app(TrainSeniors::class)->handle(trainingSeason($user));

    expect($prospect->refresh()->passing)->toBe(40);
});

it('sets a senior training focus from the training page', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);

    $this->actingAs($user)
        ->patch(route('training.focus', $player), ['focus' => 'dribbling'])
        ->assertRedirect(route('training.index'));

    expect($player->refresh()->training_focus)->toBe('dribbling');
});

it('renders the training page with senior players', function () {
    $user = User::factory()->create();
    Player::factory()->count(3)->create(['user_id' => $user->id, 'is_youth' => false]);

    $this->actingAs($user)
        ->get(route('training.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Training')
            ->has('players', 3)
            ->has('players.0.trainingFocus'),
        );
});
