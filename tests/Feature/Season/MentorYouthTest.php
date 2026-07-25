<?php

declare(strict_types=1);

use App\Actions\Season\EnsureSeason;
use App\Actions\Season\MentorYouth;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function youthFor(int $userId): Player
{
    return Player::factory()->create([
        'user_id' => $userId,
        'is_youth' => true,
        'age' => 15,
        'potential' => 100,
        'vision' => 30, 'passing' => 30, 'dribbling' => 30, 'finishing' => 30, 'tackling' => 30, 'pace' => 30,
    ]);
}

it('develops the academy an extra step while a star senior is owned', function () {
    $user = User::factory()->create();
    Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false,
        'vision' => 90, 'passing' => 90, 'dribbling' => 90, 'finishing' => 90, 'tackling' => 90, 'pace' => 90,
    ]);
    $youth = youthFor($user->id);
    $season = app(EnsureSeason::class)->handle($user);

    app(MentorYouth::class)->handle($season);

    // The weakest attribute took one development step from the mentor.
    expect($youth->refresh()->vision)->toBe(35);
});

it('does nothing without a star senior', function () {
    $user = User::factory()->create();
    Player::factory()->create([
        'user_id' => $user->id, 'is_youth' => false,
        'vision' => 50, 'passing' => 50, 'dribbling' => 50, 'finishing' => 50, 'tackling' => 50, 'pace' => 50,
    ]);
    $youth = youthFor($user->id);
    $season = app(EnsureSeason::class)->handle($user);

    app(MentorYouth::class)->handle($season);

    expect($youth->refresh()->vision)->toBe(30); // untouched
});

it('does not mentor another club\'s academy', function () {
    $mentorOwner = User::factory()->create();
    Player::factory()->create([
        'user_id' => $mentorOwner->id, 'is_youth' => false,
        'vision' => 90, 'passing' => 90, 'dribbling' => 90, 'finishing' => 90, 'tackling' => 90, 'pace' => 90,
    ]);
    $season = app(EnsureSeason::class)->handle($mentorOwner);

    $rivalYouth = youthFor(User::factory()->create()->id);

    app(MentorYouth::class)->handle($season);

    expect($rivalYouth->refresh()->vision)->toBe(30);
});

beforeEach(function () {
    Team::factory()->count(7)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);
});
