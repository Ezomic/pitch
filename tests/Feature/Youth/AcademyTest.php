<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('never fields a youth when building the default squad', function () {
    $user = User::factory()->create();
    Player::factory()->count(12)->create(); // shared seniors
    $cheapYouth = Player::factory()->youth($user->id)->create([
        'vision' => 3, 'passing' => 3, 'dribbling' => 3, 'finishing' => 3, 'tackling' => 3, 'pace' => 3,
    ]);

    $squad = app(EnsureSquad::class)->handle($user);

    expect($squad->assignments()->pluck('player_id'))->not->toContain($cheapYouth->id);
});

it('marks a prospect promotable when old or good enough', function () {
    $old = Player::factory()->youth()->create(['age' => 18, 'vision' => 20, 'passing' => 20, 'dribbling' => 20, 'finishing' => 20, 'tackling' => 20, 'pace' => 20]);
    $good = Player::factory()->youth()->create(['age' => 14, 'vision' => 70, 'passing' => 70, 'dribbling' => 70, 'finishing' => 70, 'tackling' => 70, 'pace' => 70]);
    $raw = Player::factory()->youth()->create(['age' => 14, 'vision' => 25, 'passing' => 25, 'dribbling' => 25, 'finishing' => 25, 'tackling' => 25, 'pace' => 25]);

    expect($old->isPromotable())->toBeTrue()
        ->and($good->isPromotable())->toBeTrue()
        ->and($raw->isPromotable())->toBeFalse();
});

it('renders the academy with the user\'s prospects', function () {
    $user = User::factory()->create();
    Player::factory()->youth($user->id)->count(2)->create();
    Player::factory()->youth(User::factory()->create()->id)->create(); // another club's youth

    $this->actingAs($user)
        ->get(route('youth.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Youth')
            ->has('prospects', 2)
            ->has('prospects.0.overall')
            ->has('prospects.0.potential')
            ->has('prospects.0.promotable')
            ->has('prospects.0.attributes.finishing')
            ->has('prospects.0.trainingFocus'),
        );
});

it('promotes a ready prospect into the selectable pool', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create(['age' => 18]);

    $this->actingAs($user)
        ->post(route('youth.promote', $prospect))
        ->assertRedirect(route('youth.index'));

    expect($prospect->refresh()->is_youth)->toBeFalse();

    $selectable = Player::query()->selectableFor($user->id)->pluck('id');
    expect($selectable)->toContain($prospect->id);
});

it('refuses to promote a prospect that is not ready', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create([
        'age' => 13, 'vision' => 4, 'passing' => 4, 'dribbling' => 4, 'finishing' => 4, 'tackling' => 4, 'pace' => 4,
    ]);

    $this->actingAs($user)
        ->from(route('youth.index'))
        ->post(route('youth.promote', $prospect))
        ->assertSessionHasErrors('player');

    expect($prospect->refresh()->is_youth)->toBeTrue();
});

it('sets and clears a training focus', function () {
    $user = User::factory()->create();
    $youth = Player::factory()->youth($user->id)->create();

    $this->actingAs($user)
        ->patch(route('youth.focus', $youth), ['focus' => 'finishing'])
        ->assertRedirect(route('youth.index'));
    expect($youth->refresh()->training_focus)->toBe('finishing');

    $this->actingAs($user)->patch(route('youth.focus', $youth), ['focus' => null]);
    expect($youth->refresh()->training_focus)->toBeNull();
});

it('rejects an unknown training focus', function () {
    $user = User::factory()->create();
    $youth = Player::factory()->youth($user->id)->create();

    $this->actingAs($user)
        ->from(route('youth.index'))
        ->patch(route('youth.focus', $youth), ['focus' => 'nonsense'])
        ->assertSessionHasErrors('focus');
});

it('will not set a training focus on another club\'s prospect', function () {
    $user = User::factory()->create();
    $youth = Player::factory()->youth(User::factory()->create()->id)->create();

    $this->actingAs($user)->patch(route('youth.focus', $youth), ['focus' => 'pace'])->assertNotFound();
});

it('will not promote another club\'s prospect', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth(User::factory()->create()->id)->create(['age' => 18]);

    $this->actingAs($user)->post(route('youth.promote', $prospect))->assertNotFound();
});

it('only offers the shared pool and the user\'s own graduates', function () {
    $user = User::factory()->create();
    $rival = User::factory()->create();

    $shared = Player::factory()->create(['user_id' => null, 'is_youth' => false]);
    $mine = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);
    $theirs = Player::factory()->create(['user_id' => $rival->id, 'is_youth' => false]);
    $myYouth = Player::factory()->youth($user->id)->create();

    $ids = Player::query()->selectableFor($user->id)->pluck('id');

    expect($ids)->toContain($shared->id)
        ->and($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id)
        ->and($ids)->not->toContain($myYouth->id);
});
