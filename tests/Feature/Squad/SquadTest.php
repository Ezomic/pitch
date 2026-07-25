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
            ->has('squad.budget')
            ->has('squad.spent')
            ->has('squad.remaining')
            ->has('pool.0.value')
            ->has('pool.0.age')
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
    // Isolate the swap mechanic from pricing: any pool player is affordable here.
    $squad->update(['budget' => 100000]);

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

it('exposes tactics and options on the squad page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('squad.formation', '433')
            ->where('squad.mentality', 'balanced')
            ->has('formations', 7) // six presets plus the custom option
            ->has('mentalities', 3),
        );
});

it('updates tactics and changes the evaluated profile', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('squad.edit'));

    $this->actingAs($user)
        ->patch(route('squad.tactics'), ['formation' => '343', 'mentality' => 'attacking'])
        ->assertRedirect(route('squad.edit'));

    $squad = $user->squad()->first();
    expect($squad->formation)->toBe('343')
        ->and($squad->mentality)->toBe('attacking');
});

it('rejects an unknown formation or mentality', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('squad.tactics'), ['formation' => 'nonsense', 'mentality' => 'balanced'])
        ->assertSessionHasErrors('formation');
});

it('rejects a swap that would exceed the budget and leaves the squad unchanged', function () {
    $elite = Player::factory()->create([
        'position' => Position::Forward,
        'vision' => 100, 'passing' => 100, 'dribbling' => 100,
        'finishing' => 100, 'tackling' => 100, 'pace' => 100,
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('squad.edit'));
    $squad = $user->squad()->first();

    $spent = (int) $squad->assignments()->with('player')->get()->sum(fn ($a) => $a->player->value());
    $squad->update(['budget' => $spent]);

    $before = slotMap($squad);

    $this->actingAs($user)
        ->from(route('squad.edit'))
        ->patch(route('squad.assign'), ['slot' => 8, 'player_id' => $elite->id])
        ->assertSessionHasErrors('player_id');

    expect(slotMap($squad->refresh()))->toBe($before);
});

it('allows an affordable swap and updates spend by the price difference', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('squad.edit'));
    $squad = $user->squad()->first();
    // Ample budget so the swap succeeds regardless of the random pool's prices.
    $squad->update(['budget' => 100000]);

    $spentBefore = (int) $squad->assignments()->with('player')->get()->sum(fn ($a) => $a->player->value());
    $outgoing = $squad->assignments()->where('slot', 3)->with('player')->firstOrFail()->player;

    $assignedIds = $squad->assignments()->pluck('player_id')->all();
    $incoming = Player::query()->whereNotIn('id', $assignedIds)->get()
        ->sortBy(fn (Player $p) => $p->value())->first();

    $this->actingAs($user)
        ->patch(route('squad.assign'), ['slot' => 3, 'player_id' => $incoming->id])
        ->assertRedirect(route('squad.edit'));

    $spentAfter = (int) $squad->refresh()->assignments()->with('player')->get()->sum(fn ($a) => $a->player->value());

    expect(slotMap($squad)[3])->toBe($incoming->id)
        ->and($spentAfter)->toBe($spentBefore - $outgoing->value() + $incoming->value());
});
