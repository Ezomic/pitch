<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\User;
use Database\Seeders\PlayerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function playerRated(int $rating): Player
{
    return Player::factory()->make([
        'vision' => $rating,
        'passing' => $rating,
        'dribbling' => $rating,
        'finishing' => $rating,
        'tackling' => $rating,
        'pace' => $rating,
    ]);
}

it('prices elite players convexly higher than average and poor ones', function () {
    $elite = playerRated(100)->value();
    $average = playerRated(50)->value();
    $poor = playerRated(25)->value();

    expect($elite)->toBeGreaterThan($average)
        ->and($average)->toBeGreaterThan($poor)
        ->and($elite / $average)->toBeGreaterThan($average / $poor);
});

it('is deterministic and rises when any attribute rises', function () {
    $base = playerRated(50);
    $sharper = Player::factory()->make([
        'vision' => 80,
        'passing' => 50,
        'dribbling' => 50,
        'finishing' => 50,
        'tackling' => 50,
        'pace' => 50,
    ]);

    expect($base->value())->toBe(playerRated(50)->value())
        ->and($sharper->value())->toBeGreaterThan($base->value());
});

it('builds a default squad within budget from the seeded pool', function () {
    $this->seed(PlayerSeeder::class);

    $squad = app(EnsureSquad::class)->handle(User::factory()->create());

    $spent = (int) $squad->assignments()->with('player')->get()
        ->sum(fn ($assignment) => $assignment->player->value());

    expect($spent)->toBeLessThanOrEqual($squad->budget);
});
