<?php

declare(strict_types=1);

use App\Actions\Season\RateClubs;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function club(string $name, int $division, int $rating): Team
{
    return Team::factory()->create([
        'name' => $name,
        'division' => $division,
        'is_youth' => false,
        'vision' => $rating, 'passing' => $rating, 'dribbling' => $rating,
        'finishing' => $rating, 'tackling' => $rating, 'pace' => $rating,
        'keeping' => $rating, 'set_pieces' => $rating,
    ]);
}

it('rates a club against the world and against its own division', function () {
    $strong = club('Elite FC', 1, 90);
    $mid = club('Middling Rovers', 1, 70);
    $bigFishSmallPond = club('Pond Kings', 2, 55);
    $weak = club('Sunday League', 2, 30);

    $rated = app(RateClubs::class)->handle();

    // Best in the world is best in its league too.
    expect($rated[$strong->id]['world'])->toBe(5.0)
        ->and($rated[$strong->id]['league'])->toBe(5.0);

    // The point of two ratings: top of a weak division, ordinary in the world.
    expect($rated[$bigFishSmallPond->id]['league'])->toBe(5.0)
        ->and($rated[$bigFishSmallPond->id]['world'])->toBeLessThan(5.0);

    // A stronger club always outranks a weaker one in the world.
    expect($rated[$mid->id]['world'])->toBeGreaterThan($rated[$bigFishSmallPond->id]['world'])
        ->and($rated[$weak->id]['world'])->toBe(1.0);
});

it('ranks the manager alongside the clubs', function () {
    $strong = ['vision' => 80, 'passing' => 80, 'dribbling' => 80, 'finishing' => 80, 'tackling' => 80, 'pace' => 80];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);

    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);

    club('Rival', $squad->division, 40);

    $rated = app(RateClubs::class)->handle($squad);

    expect($rated)->toHaveKey(RateClubs::USER_KEY)
        ->and($rated[RateClubs::USER_KEY]['league'])->toBe(5.0);
});

it('rates nothing when there are no clubs', function () {
    expect(app(RateClubs::class)->handle())->toBe([]);
});
