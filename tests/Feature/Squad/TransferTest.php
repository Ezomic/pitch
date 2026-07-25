<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\SellPlayer;
use App\Actions\Squad\SignPlayer;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('signs a free agent, spending bank money and taking ownership', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 300]);
    $agent = Player::factory()->create(['is_free_agent' => true, 'user_id' => null]);
    $price = $agent->value();

    app(SignPlayer::class)->handle($squad, $agent);

    $agent->refresh();
    expect($agent->is_free_agent)->toBeFalse()
        ->and($agent->user_id)->toBe($user->id)
        ->and($squad->refresh()->bank)->toBe(300 - $price)
        ->and(Player::query()->selectableFor($user->id)->pluck('id'))->toContain($agent->id);
});

it('refuses to sign beyond the bank', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 0]);
    $agent = Player::factory()->create(['is_free_agent' => true, 'user_id' => null, 'finishing' => 90, 'vision' => 90, 'passing' => 90, 'dribbling' => 90, 'tackling' => 90, 'pace' => 90]);

    expect(fn () => app(SignPlayer::class)->handle($squad, $agent))
        ->toThrow(ValidationException::class);
    expect($agent->refresh()->is_free_agent)->toBeTrue();
});

it('sells an owned player back to the market for bank money', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);
    $squad->assignments()->create(['player_id' => $player->id, 'slot' => 1]);
    $price = $player->value();

    app(SellPlayer::class)->handle($squad, $player);

    $player->refresh();
    expect($player->user_id)->toBeNull()
        ->and($player->is_free_agent)->toBeTrue()
        ->and($squad->refresh()->bank)->toBe(100 + $price)
        ->and($squad->assignments()->where('player_id', $player->id)->exists())->toBeFalse();
});

it('renders the transfer market with finances and contract details', function () {
    $user = User::factory()->create();
    app(EnsureSquad::class)->handle($user);
    Player::factory()->create(['is_free_agent' => true, 'user_id' => null]);
    Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);

    $this->actingAs($user)
        ->get(route('transfers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transfers')
            ->has('bank')
            ->has('finances', fn (Assert $finances) => $finances
                ->has('income')
                ->has('wageBill')
                ->has('net'),
            )
            ->has('market.0.value')
            ->has('market.0.wage')
            ->has('owned.0.wage')
            ->has('owned.0.contractYears'),
        );
});
