<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\SellPlayer;
use App\Actions\Squad\SignPlayer;
use App\Models\Career;
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
        ->and($agent->career_id)->toBe($squad->career_id)
        ->and($squad->refresh()->bank)->toBe(300 - $price)
        ->and(Player::query()->selectableFor($squad->career_id)->pluck('id'))->toContain($agent->id);
});

it('keeps a signed free agent out of every other career', function () {
    $buyer = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($buyer);
    $squad->forceFill(['bank' => 1000])->save();
    $agent = Player::factory()->create(['is_free_agent' => true, 'user_id' => null]);

    app(SignPlayer::class)->handle($squad, $agent);

    $other = Career::factory()->create();

    expect(Player::query()->selectableFor($other->id)->pluck('id'))->not->toContain($agent->id);

    $this->actingAs($buyer)
        ->get(route('transfers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('owned', fn ($owned) => collect($owned)->pluck('id')->contains($agent->id)),
        );
});

it('keeps a player sold in one career off every other career\'s market', function () {
    $seller = User::factory()->create();
    $sellerSquad = app(EnsureSquad::class)->handle($seller);
    $player = Player::factory()->create(['user_id' => $seller->id, 'is_youth' => false]);
    app(SellPlayer::class)->handle($sellerSquad, $player);

    $buyer = User::factory()->create();
    $buyerSquad = app(EnsureSquad::class)->handle($buyer);
    $buyerSquad->forceFill(['bank' => 1000])->save();

    $this->actingAs($buyer)
        ->get(route('transfers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('market', fn ($market) => ! collect($market)->pluck('id')->contains($player->id)),
        );

    expect(fn () => app(SignPlayer::class)->handle($buyerSquad, $player))
        ->toThrow(ValidationException::class);
    expect($player->refresh()->career_id)->toBe($sellerSquad->career_id)
        ->and($player->is_free_agent)->toBeTrue();

    $this->actingAs($seller)
        ->get(route('transfers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('market', fn ($market) => collect($market)->pluck('id')->contains($player->id))
            ->where('owned', fn ($owned) => ! collect($owned)->pluck('id')->contains($player->id)),
        );
});

it('gives players signed before career stamping back to their career', function () {
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);
    $signed = Player::factory()->create(['is_free_agent' => false]);
    $signed->forceFill(['user_id' => $user->id, 'career_id' => null])->save();
    $pool = Player::factory()->create(['is_free_agent' => false, 'user_id' => null]);

    (require database_path('migrations/2026_11_14_120000_claim_signed_players_for_their_career.php'))->up();

    expect($signed->refresh()->career_id)->toBe($squad->career_id)
        ->and($pool->refresh()->career_id)->toBeNull();
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
