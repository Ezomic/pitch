<?php

declare(strict_types=1);

use App\Actions\Season\AgePlayers;
use App\Actions\Season\PayWages;
use App\Actions\Squad\RenewContract;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('derives a higher weekly wage from a more valuable player', function () {
    $star = Player::factory()->create(['vision' => 95, 'passing' => 95, 'dribbling' => 95, 'finishing' => 95, 'tackling' => 95, 'pace' => 95]);
    $journeyman = Player::factory()->create(['vision' => 30, 'passing' => 30, 'dribbling' => 30, 'finishing' => 30, 'tackling' => 30, 'pace' => 30]);

    expect($star->weeklyWage())->toBeGreaterThan($journeyman->weeklyWage())
        ->and($journeyman->weeklyWage())->toBeGreaterThanOrEqual(1);
});

it('draws the wage bill against income each week, moving the bank', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100, 'weekly_income' => 20]);
    Player::factory()->count(3)->create(['user_id' => $user->id, 'is_youth' => false]);

    $wageBill = $squad->wageBill();
    expect($wageBill)->toBeGreaterThan(0);

    app(PayWages::class)->handle($squad);

    expect($squad->refresh()->bank)->toBe(100 - $wageBill + 20);
});

it('does not pay wages for academy prospects', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100, 'weekly_income' => 20]);
    Player::factory()->youth($user->id)->count(3)->create();

    expect($squad->wageBill())->toBe(0);
});

it('drops squad morale a notch when wages push the bank into the red', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 0, 'weekly_income' => 0]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'form' => 2]);

    app(PayWages::class)->handle($squad);

    expect($squad->refresh()->bank)->toBeLessThan(0)
        ->and($player->refresh()->form)->toBe(1);
});

it('renews a contract for a signing-on fee', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 500]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'contract_years' => 1]);
    $fee = $player->weeklyWage() * 12;

    app(RenewContract::class)->handle($squad, $player);

    expect($player->refresh()->contract_years)->toBe(Player::DEFAULT_CONTRACT_YEARS)
        ->and($squad->refresh()->bank)->toBe(500 - $fee);
});

it('refuses to renew a contract the bank cannot cover', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 0]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'contract_years' => 1]);

    expect(fn () => app(RenewContract::class)->handle($squad, $player))
        ->toThrow(ValidationException::class);
    expect($player->refresh()->contract_years)->toBe(1);
});

it('winds a senior contract down a year at rollover', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'age' => 25, 'contract_years' => 3]);

    app(AgePlayers::class)->handle($user);

    expect($player->refresh()->contract_years)->toBe(2)
        ->and($player->user_id)->toBe($user->id);
});

it('releases a senior to the market when their contract expires', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'age' => 25, 'contract_years' => 1]);
    $squad->assignments()->create(['player_id' => $player->id, 'slot' => 1]);

    app(AgePlayers::class)->handle($user);

    $player->refresh();
    expect($player->contract_years)->toBe(0)
        ->and($player->user_id)->toBeNull()
        ->and($player->is_free_agent)->toBeTrue()
        ->and($squad->assignments()->where('player_id', $player->id)->exists())->toBeFalse();
});
