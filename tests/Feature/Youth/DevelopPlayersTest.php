<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\DevelopPlayers;
use App\Actions\Season\EnsureSeason;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function prospect(array $overrides = []): Player
{
    return Player::factory()->create([
        'is_youth' => true,
        'age' => 15,
        'potential' => 60,
        'vision' => 25,
        'passing' => 30,
        'dribbling' => 30,
        'finishing' => 30,
        'tackling' => 30,
        'pace' => 30,
        ...$overrides,
    ]);
}

it('grows a prospect weakest-first toward its potential', function () {
    $youth = prospect();

    app(DevelopPlayers::class)->handle([$youth]);

    expect($youth->refresh()->vision)->toBe(30); // the lowest attribute gained a step
});

it('stops a prospect at its potential and never overshoots', function () {
    $youth = prospect(['potential' => 60]);

    foreach (range(1, 200) as $ignored) {
        app(DevelopPlayers::class)->handle([$youth]);
    }

    expect($youth->refresh()->attributes()->overall())->toBe(60);
});

it('lets a higher-potential prospect end up stronger than a lower one', function () {
    $modest = prospect(['potential' => 65]);
    $gem = prospect(['potential' => 95]);

    foreach (range(1, 300) as $ignored) {
        app(DevelopPlayers::class)->handle([$modest, $gem]);
    }

    expect($gem->refresh()->attributes()->overall())
        ->toBeGreaterThan($modest->refresh()->attributes()->overall());
});

it('does not develop seniors', function () {
    $senior = Player::factory()->create([
        'is_youth' => false,
        'potential' => 100,
        'vision' => 25,
    ]);

    app(DevelopPlayers::class)->handle([$senior]);

    expect($senior->refresh()->vision)->toBe(25);
});

it('develops the user\'s youth when the week advances', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $youth = prospect(['user_id' => $user->id]);
    $before = $youth->attributes()->overall();

    app(AdvanceWeek::class)->handle($season);

    expect($youth->refresh()->attributes()->overall())->toBeGreaterThanOrEqual($before)
        ->and($youth->vision)->toBe(30);
});
