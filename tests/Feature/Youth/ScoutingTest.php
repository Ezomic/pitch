<?php

declare(strict_types=1);

use App\Actions\Scout\EnsureScouts;
use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\DeliverProspects;
use App\Actions\Season\EnsureSeason;
use App\Enums\ScoutStatus;
use App\Models\Player;
use App\Models\Scout;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('stocks a market of hireable scouts', function () {
    $user = User::factory()->create();

    app(EnsureScouts::class)->handle($user);

    expect($user->scouts()->where('status', ScoutStatus::Available)->count())->toBe(3);
});

it('renders the scouting page with staff and market', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('scouts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Scouting')
            ->has('budget')
            ->has('currentDate')
            ->has('market', 3),
        );
});

it('hires a scout, paying the fee from the budget', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test Squad', 'budget' => 100]);
    $scout = Scout::factory()->for($user)->create(['rating' => 4]); // costs 20

    $this->actingAs($user)
        ->post(route('scouts.hire', $scout))
        ->assertRedirect(route('scouts.index'));

    expect($scout->refresh()->status)->toBe(ScoutStatus::Idle)
        ->and($squad->refresh()->budget)->toBe(80);
});

it('rejects hiring a scout the club cannot afford', function () {
    $user = User::factory()->create();
    Squad::create(['user_id' => $user->id, 'name' => 'Test Squad', 'budget' => 5]);
    $scout = Scout::factory()->for($user)->create(['rating' => 5]); // costs 25

    $this->actingAs($user)
        ->from(route('scouts.index'))
        ->post(route('scouts.hire', $scout))
        ->assertSessionHasErrors('scout');

    expect($scout->refresh()->status)->toBe(ScoutStatus::Available);
});

it('sends an idle scout out and recalls it', function () {
    $user = User::factory()->create();
    app(EnsureSeason::class)->handle($user);
    $scout = Scout::factory()->for($user)->idle()->create();

    $this->actingAs($user)->post(route('scouts.assign', $scout))->assertRedirect(route('scouts.index'));
    $scout->refresh();
    expect($scout->status)->toBe(ScoutStatus::Scouting)
        ->and($scout->next_delivery_on)->not->toBeNull();

    $this->actingAs($user)->post(route('scouts.recall', $scout))->assertRedirect(route('scouts.index'));
    expect($scout->refresh()->status)->toBe(ScoutStatus::Idle)
        ->and($scout->next_delivery_on)->toBeNull();
});

it('will not touch another club\'s scout', function () {
    $user = User::factory()->create();
    $scout = Scout::factory()->for(User::factory()->create())->idle()->create();

    $this->actingAs($user)->post(route('scouts.assign', $scout))->assertNotFound();
});

it('delivers 1-3 youth from a scout whose intake is due', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    Scout::factory()->for($user)->scouting()->create([
        'rating' => 3,
        'next_delivery_on' => $season->current_date->copy()->subDay(),
    ]);

    app(DeliverProspects::class)->handle($season);

    $youth = Player::query()->where('user_id', $user->id)->where('is_youth', true)->get();
    expect($youth->count())->toBeGreaterThanOrEqual(1)
        ->and($youth->count())->toBeLessThanOrEqual(3);
    $youth->each(function (Player $p) {
        expect($p->age)->toBeGreaterThanOrEqual(12)->toBeLessThanOrEqual(18);
    });
});

it('does not deliver before the intake date', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    Scout::factory()->for($user)->scouting()->create([
        'next_delivery_on' => $season->current_date->copy()->addWeeks(3),
    ]);

    app(DeliverProspects::class)->handle($season);

    expect(Player::query()->where('user_id', $user->id)->where('is_youth', true)->count())->toBe(0);
});

it('delivers youth when the week advances', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    Scout::factory()->for($user)->scouting()->create([
        'next_delivery_on' => $season->current_date->copy()->addDay(),
    ]);

    app(AdvanceWeek::class)->handle($season);

    expect(Player::query()->where('user_id', $user->id)->where('is_youth', true)->count())->toBeGreaterThanOrEqual(1);
});
