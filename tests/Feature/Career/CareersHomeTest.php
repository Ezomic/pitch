<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Models\Career;
use App\Models\Player;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
    Team::factory()->count(7)->create(['is_youth' => false]);
});

it('lists the saves a manager holds, marking the one being played', function () {
    $user = User::factory()->create();
    $first = $user->currentCareer();

    $this->actingAs($user)->post(route('careers.store'), ['name' => 'Second save'])
        ->assertRedirect(route('careers.index'));

    $this->actingAs($user)->get(route('careers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Careers')
            ->has('careers', 2)
            // Creating one switches to it, and it is the most recently played.
            ->where('careers.0.name', 'Second save')
            ->where('careers.0.active', true)
            ->where('careers.1.active', false));

    expect($user->fresh()->current_career_id)->not->toBe($first->id);
});

it('switches between saves', function () {
    $user = User::factory()->create();
    $first = $user->currentCareer();

    $this->actingAs($user)->post(route('careers.store'), ['name' => 'Second']);
    $second = $user->careers()->where('name', 'Second')->firstOrFail();

    expect($user->fresh()->current_career_id)->toBe($second->id);

    $this->actingAs($user)->post(route('careers.switch', $first))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_career_id)->toBe($first->id);
});

it('renames a save', function () {
    $user = User::factory()->create();
    $career = $user->currentCareer();

    $this->actingAs($user)->patch(route('careers.rename', $career), ['name' => 'The comeback'])
        ->assertRedirect(route('careers.index'));

    expect($career->fresh()->name)->toBe('The comeback');
});

it('refuses a nameless save', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('careers.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($user->careers()->count())->toBe(0);
});

it('deletes a save and everything in it', function () {
    $user = User::factory()->create();
    $first = $user->currentCareer();
    app(EnsureSquad::class)->handle($user);

    expect(Squad::query()->where('career_id', $first->id)->count())->toBe(1);

    $this->actingAs($user)->post(route('careers.store'), ['name' => 'Second']);
    $second = $user->careers()->where('name', 'Second')->firstOrFail();

    $this->actingAs($user)->delete(route('careers.destroy', $first))
        ->assertRedirect(route('careers.index'));

    // The save is gone, and the squad went with it.
    expect(Career::query()->find($first->id))->toBeNull()
        ->and(Squad::query()->where('career_id', $first->id)->count())->toBe(0)
        ->and($user->fresh()->current_career_id)->toBe($second->id);
});

it('will not let a manager delete their only save', function () {
    $user = User::factory()->create();
    $only = $user->currentCareer();

    $this->actingAs($user)->delete(route('careers.destroy', $only))
        ->assertSessionHasErrors('career');

    expect(Career::query()->find($only->id))->not->toBeNull();
});

it('keeps one manager out of another manager\'s saves', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $career = $user->currentCareer();
    $other->currentCareer();

    $this->actingAs($other)->post(route('careers.switch', $career))->assertForbidden();
    $this->actingAs($other)->patch(route('careers.rename', $career), ['name' => 'Mine now'])->assertForbidden();
    $this->actingAs($other)->delete(route('careers.destroy', $career))->assertForbidden();

    expect($career->fresh()->name)->not->toBe('Mine now');
});
