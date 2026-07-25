<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Domain\Position;
use App\Sim\Engine\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('penalises a player fielded out of position', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 500]);

    // Slot 1 in the default 4-3-3 is a defender slot; field a forward there.
    $forward = Player::factory()->create([
        'position' => Position::Forward, 'fitness' => 100, 'form' => 0, 'trait' => null,
        'vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60,
    ]);
    $squad->assignments()->create(['player_id' => $forward->id, 'slot' => 1]);

    $slotPosition = Formation::fromId($squad->formation)->layout[1][1];
    $bySlot = $squad->attributesBySlot();

    expect($slotPosition)->toBe(Position::Defender)
        ->and($bySlot[1]->finishing)->toBe(54); // 60 * 0.9, off position
});

it('leaves a player in their natural position at full strength', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 500]);

    $defender = Player::factory()->create([
        'position' => Position::Defender, 'fitness' => 100, 'form' => 0, 'trait' => null,
        'vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60,
    ]);
    $squad->assignments()->create(['player_id' => $defender->id, 'slot' => 1]);

    expect($squad->attributesBySlot()[1]->tackling)->toBe(60); // in position, untouched
});
