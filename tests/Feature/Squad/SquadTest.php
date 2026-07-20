<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Player::factory()->count(6)->create(['position' => Position::Defender]);
    Player::factory()->count(6)->create(['position' => Position::Midfielder]);
    Player::factory()->count(6)->create(['position' => Position::Forward]);
});

function slotMap(Squad $squad): array
{
    return $squad->assignments()->get()
        ->mapWithKeys(fn ($a) => [$a->slot => $a->player_id])
        ->all();
}

it('creates and renders a default squad on first visit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Squad')
            ->has('squad.slots', 10)
            ->has('pool')
            ->has('profile.meanDecisionGap')
            ->has('profile.chancesPer90')
            ->has('profile.chancesConcededPer90')
            ->has('profile.goalsConcededPer90'),
        );

    $squad = $user->squad()->first();
    expect($squad)->not->toBeNull()
        ->and($squad->assignments()->count())->toBe(10);
});

it('swaps a pool player into a slot', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('squad.edit'));
    $squad = $user->squad()->first();

    $assignedIds = $squad->assignments()->pluck('player_id')->all();
    $incoming = Player::query()->whereNotIn('id', $assignedIds)->firstOrFail();

    $this->actingAs($user)
        ->patch(route('squad.assign'), ['slot' => 3, 'player_id' => $incoming->id])
        ->assertRedirect(route('squad.edit'));

    expect(slotMap($squad->refresh())[3])->toBe($incoming->id)
        ->and($squad->assignments()->count())->toBe(10);
});

it('swaps slots when assigning a player already in the squad', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('squad.edit'));
    $squad = $user->squad()->first();

    $before = slotMap($squad);
    $playerInSlot5 = $before[5];
    $playerInSlot3 = $before[3];

    $this->actingAs($user)
        ->patch(route('squad.assign'), ['slot' => 3, 'player_id' => $playerInSlot5]);

    $after = slotMap($squad->refresh());

    expect($after[3])->toBe($playerInSlot5)
        ->and($after[5])->toBe($playerInSlot3)
        ->and($squad->assignments()->count())->toBe(10);
});

it('rejects an invalid slot', function () {
    $user = User::factory()->create();
    $player = Player::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('squad.assign'), ['slot' => 11, 'player_id' => $player->id])
        ->assertSessionHasErrors('slot');
});

it('rejects an unknown player', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('squad.assign'), ['slot' => 3, 'player_id' => 99999])
        ->assertSessionHasErrors('player_id');
});

it('requires authentication', function () {
    $this->get(route('squad.edit'))->assertRedirect(route('login'));
});
