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
    $elite = playerRated(20)->value();
    $average = playerRated(10)->value();
    $poor = playerRated(5)->value();

    expect($elite)->toBeGreaterThan($average)
        ->and($average)->toBeGreaterThan($poor)
        ->and($elite / $average)->toBeGreaterThan($average / $poor);
});

it('is deterministic and rises when any attribute rises', function () {
    $base = playerRated(10);
    $sharper = Player::factory()->make([
        'vision' => 16,
        'passing' => 10,
        'dribbling' => 10,
        'finishing' => 10,
        'tackling' => 10,
        'pace' => 10,
    ]);

    expect($base->value())->toBe(playerRated(10)->value())
        ->and($sharper->value())->toBeGreaterThan($base->value());
});

it('builds a default squad within budget from the seeded pool', function () {
    $this->seed(PlayerSeeder::class);

    $squad = app(EnsureSquad::class)->handle(User::factory()->create());

    $spent = (int) $squad->assignments()->with('player')->get()
        ->sum(fn ($assignment) => $assignment->player->value());

    expect($spent)->toBeLessThanOrEqual($squad->budget);
});
