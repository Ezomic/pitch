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
        'potential' => 12,
        'vision' => 5,
        'passing' => 6,
        'dribbling' => 6,
        'finishing' => 6,
        'tackling' => 6,
        'pace' => 6,
        ...$overrides,
    ]);
}

it('trains the focused attribute instead of the weakest when a focus is set', function () {
    $youth = prospect(['potential' => 20, 'training_focus' => 'finishing', 'vision' => 4, 'finishing' => 6]);

    app(DevelopPlayers::class)->handle([$youth]);

    $youth->refresh();
    expect($youth->finishing)->toBe(7) // the focus grew
        ->and($youth->vision)->toBe(4); // the weakest was left alone
});

it('falls back to the weakest attribute when the focus is maxed out', function () {
    $youth = prospect(['potential' => 20, 'training_focus' => 'finishing', 'finishing' => 20, 'vision' => 4]);

    app(DevelopPlayers::class)->handle([$youth]);

    expect($youth->refresh()->vision)->toBe(5);
});

it('grows a prospect weakest-first toward its potential', function () {
    $youth = prospect();

    app(DevelopPlayers::class)->handle([$youth]);

    expect($youth->refresh()->vision)->toBe(6); // the lowest attribute gained a point
});

it('stops a prospect at its potential and never overshoots', function () {
    $youth = prospect(['potential' => 12]);

    foreach (range(1, 200) as $ignored) {
        app(DevelopPlayers::class)->handle([$youth]);
    }

    expect($youth->refresh()->attributes()->overall())->toBe(12);
});

it('lets a higher-potential prospect end up stronger than a lower one', function () {
    $modest = prospect(['potential' => 13]);
    $gem = prospect(['potential' => 19]);

    foreach (range(1, 300) as $ignored) {
        app(DevelopPlayers::class)->handle([$modest, $gem]);
    }

    expect($gem->refresh()->attributes()->overall())
        ->toBeGreaterThan($modest->refresh()->attributes()->overall());
});

it('does not develop seniors', function () {
    $senior = Player::factory()->create([
        'is_youth' => false,
        'potential' => 20,
        'vision' => 5,
    ]);

    app(DevelopPlayers::class)->handle([$senior]);

    expect($senior->refresh()->vision)->toBe(5);
});

it('develops the user\'s youth when the week advances', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $youth = prospect(['user_id' => $user->id]);
    $before = $youth->attributes()->overall();

    app(AdvanceWeek::class)->handle($season);

    expect($youth->refresh()->attributes()->overall())->toBeGreaterThanOrEqual($before)
        ->and($youth->vision)->toBe(6);
});
