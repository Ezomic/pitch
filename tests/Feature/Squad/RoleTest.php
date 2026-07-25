<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redistributes attributes for a role', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 500]);

    // Slot 1 is a defender slot; put a defender there so there is no off-position penalty.
    $player = Player::factory()->create([
        'position' => Position::Defender, 'fitness' => 100, 'form' => 0, 'trait' => null,
        'vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60,
    ]);
    $squad->assignments()->create(['player_id' => $player->id, 'slot' => 1, 'role' => 'ball_playing']);

    $bySlot = $squad->attributesBySlot();

    expect($bySlot[1]->passing)->toBe(70) // +10 ball-playing
        ->and($bySlot[1]->tackling)->toBe(50); // -10 ball-playing
});

it('sets and clears a slot role', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $this->actingAs($user)->get(route('squad.edit')); // builds the default squad
    $squad = $user->squad;

    $this->actingAs($user)
        ->patch(route('squad.role'), ['slot' => 2, 'role' => 'anchor'])
        ->assertRedirect(route('squad.edit'));
    expect($squad->assignments()->where('slot', 2)->value('role'))->toBe('anchor');

    $this->actingAs($user)->patch(route('squad.role'), ['slot' => 2, 'role' => null]);
    expect($squad->assignments()->where('slot', 2)->value('role'))->toBeNull();
});

it('rejects an unknown role', function () {
    $user = User::factory()->create();
    Player::factory()->count(16)->create();
    $this->actingAs($user)->get(route('squad.edit'));

    $this->actingAs($user)
        ->from(route('squad.edit'))
        ->patch(route('squad.role'), ['slot' => 1, 'role' => 'nonsense'])
        ->assertSessionHasErrors('role');
});
