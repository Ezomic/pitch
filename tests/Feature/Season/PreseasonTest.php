<?php

declare(strict_types=1);

use App\Actions\Season\AgePlayers;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\GenerateYouthIntake;
use App\Actions\Season\RolloverSeason;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(7)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);
});

it('ages every owned player by a year', function () {
    $user = User::factory()->create();
    $player = Player::factory()->create(['user_id' => $user->id, 'age' => 24, 'is_youth' => false]);

    app(AgePlayers::class)->handle($user);

    expect($player->refresh()->age)->toBe(25);
});

it('regresses a senior past their peak', function () {
    $user = User::factory()->create();
    $veteran = Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false, 'age' => 33, 'finishing' => 80,
    ]);
    $prime = Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false, 'age' => 24, 'finishing' => 80,
    ]);

    app(AgePlayers::class)->handle($user);

    expect($veteran->refresh()->finishing)->toBeLessThan(80) // declined past peak
        ->and($prime->refresh()->finishing)->toBe(80); // still in prime
});

it('does not age another club\'s players', function () {
    $user = User::factory()->create();
    $theirs = Player::factory()->create(['user_id' => User::factory()->create()->id, 'age' => 24]);

    app(AgePlayers::class)->handle($user);

    expect($theirs->refresh()->age)->toBe(24);
});

it('brings in a fresh crop of youth prospects', function () {
    $user = User::factory()->create();

    app(GenerateYouthIntake::class)->handle($user);

    $youth = Player::query()->where('user_id', $user->id)->where('is_youth', true)->get();
    expect($youth)->toHaveCount(3);
    expect($youth->every(fn (Player $p) => $p->potential >= 60 && $p->age <= 17))->toBeTrue();
});

it('ages the squad and delivers youth on season rollover', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $player = Player::factory()->create(['user_id' => $user->id, 'age' => 26, 'is_youth' => false]);

    app(RolloverSeason::class)->handle($season);

    expect($player->refresh()->age)->toBe(27)
        ->and(Player::query()->where('user_id', $user->id)->where('is_youth', true)->count())->toBe(3);
});
