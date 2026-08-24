<?php

declare(strict_types=1);

use App\Actions\Career\ClaimClub;
use App\Actions\Career\CreateCareer;
use App\Actions\Career\InviteToLeague;
use App\Models\Career;
use App\Models\CareerInvitation;
use App\Models\CareerMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(6)->create(['is_youth' => false]);
});

function league(User $owner, string $name = 'Sunday League'): Career
{
    return app(CreateCareer::class)->handle($owner, $name, Career::LEAGUE);
}

function inviteTo(Career $career, User $owner): CareerInvitation
{
    return app(InviteToLeague::class)->handle($career, $owner);
}

it('seats whoever starts a league as its owner', function () {
    $owner = User::factory()->create();
    $career = league($owner);

    $seat = $career->seatFor($owner);

    expect($career->isLeague())->toBeTrue()
        ->and($seat)->toBeInstanceOf(CareerMembership::class)
        ->and($seat->isOwner())->toBeTrue()
        ->and($seat->team_id)->toBeNull();
});

it('shows the lobby to a seated manager', function () {
    $owner = User::factory()->create();
    $career = league($owner);

    $this->actingAs($owner)->get(route('league.show', $career))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('League')
            ->where('isOwner', true)
            ->where('yourTeamId', null)
            ->has('members', 1)
            ->where('members.0.you', true)
            // Nobody has claimed anything, so every senior club is on offer.
            ->has('availableClubs', 6));
});

it('keeps outsiders out of a league, and hides solo saves entirely', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $career = league($owner);
    $solo = app(CreateCareer::class)->handle($owner, 'Just me');

    $this->actingAs($stranger)->get(route('league.show', $career))->assertForbidden();
    $this->actingAs($owner)->get(route('league.show', $solo))->assertNotFound();
});

it('lets a manager in on an invitation and switches them to that save', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $invitation = inviteTo($career, $owner);

    $this->actingAs($joiner)->get(route('league.join', $invitation->token))
        ->assertRedirect(route('league.show', $career->id));

    expect($career->seatFor($joiner))->toBeInstanceOf(CareerMembership::class)
        ->and($career->seatFor($joiner)->isOwner())->toBeFalse()
        ->and($joiner->fresh()->current_career_id)->toBe($career->id)
        ->and($invitation->fresh()->status())->toBe('used');
});

it('will not let one invitation seat two managers', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $career = league($owner);
    $invitation = inviteTo($career, $owner);

    $this->actingAs($first)->get(route('league.join', $invitation->token));

    $this->actingAs($second)->get(route('league.join', $invitation->token))
        ->assertRedirect(route('careers.index'))
        ->assertSessionHasErrors('career');

    expect($career->memberships()->count())->toBe(2);
});

it('turns an expired invitation away', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $invitation = app(InviteToLeague::class)->handle($career, $owner, -1);

    $this->actingAs($joiner)->get(route('league.join', $invitation->token))
        ->assertSessionHasErrors('career');

    expect($career->seatFor($joiner))->toBeNull();
});

it('leaves an invitation open when the manager is already in', function () {
    $owner = User::factory()->create();
    $career = league($owner);
    $invitation = inviteTo($career, $owner);

    $this->actingAs($owner)->get(route('league.join', $invitation->token));

    expect($invitation->fresh()->status())->toBe('open')
        ->and($career->memberships()->count())->toBe(1);
});

it('claims a club, and drops it from what the next manager can take', function () {
    $owner = User::factory()->create();
    $career = league($owner);
    $team = Team::query()->where('is_youth', false)->firstOrFail();

    $this->actingAs($owner)->post(route('league.claim', ['career' => $career, 'team' => $team]))
        ->assertRedirect(route('league.show', $career));

    expect($career->seatFor($owner)->team_id)->toBe($team->id);

    $this->actingAs($owner)->get(route('league.show', $career))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yourTeamId', $team->id)
            ->has('availableClubs', 5));
});

it('refuses a club another manager already runs', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $this->actingAs($joiner)->get(route('league.join', inviteTo($career, $owner)->token));

    $team = Team::query()->where('is_youth', false)->firstOrFail();
    app(ClaimClub::class)->handle($career->seatFor($owner), $team);

    $this->actingAs($joiner)->post(route('league.claim', ['career' => $career, 'team' => $team]))
        ->assertSessionHasErrors('club');

    expect($career->seatFor($joiner)->team_id)->toBeNull();
});

it('gives a club back to the AI when released', function () {
    $owner = User::factory()->create();
    $career = league($owner);
    $team = Team::query()->where('is_youth', false)->firstOrFail();
    app(ClaimClub::class)->handle($career->seatFor($owner), $team);

    $this->actingAs($owner)->delete(route('league.release', $career))
        ->assertRedirect(route('league.show', $career));

    expect($career->seatFor($owner)->team_id)->toBeNull();
});

it('lets the owner remove a manager, but never themselves', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $this->actingAs($joiner)->get(route('league.join', inviteTo($career, $owner)->token));

    $joinerSeat = $career->seatFor($joiner);
    $ownerSeat = $career->seatFor($owner);

    $this->actingAs($owner)->delete(route('league.remove', ['career' => $career, 'member' => $ownerSeat]))
        ->assertSessionHasErrors('member');

    $this->actingAs($owner)->delete(route('league.remove', ['career' => $career, 'member' => $joinerSeat]))
        ->assertRedirect(route('league.show', $career));

    expect($career->seatFor($joiner))->toBeNull()
        ->and($career->seatFor($owner))->not->toBeNull();
});

it('does not let a member remove anyone, or mint invitations', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $this->actingAs($joiner)->get(route('league.join', inviteTo($career, $owner)->token));

    $ownerSeat = $career->seatFor($owner);

    $this->actingAs($joiner)->post(route('league.invite', $career))->assertForbidden();
    $this->actingAs($joiner)->delete(route('league.remove', ['career' => $career, 'member' => $ownerSeat]))
        ->assertForbidden();

    expect($career->invitations()->count())->toBe(1);
});

it('hides invitations from everyone but the owner', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();
    $career = league($owner);
    $this->actingAs($joiner)->get(route('league.join', inviteTo($career, $owner)->token));

    $this->actingAs($joiner)->get(route('league.show', $career))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwner', false)
            ->has('invitations', 0)
            ->has('members', 2));

    $this->actingAs($owner)->get(route('league.show', $career))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1));
});

it('starts a league save from the careers page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('careers.store'), ['name' => 'Our league', 'type' => 'league'])
        ->assertRedirect(route('careers.index'));

    $career = $user->careers()->where('name', 'Our league')->firstOrFail();

    expect($career->isLeague())->toBeTrue()
        ->and($career->seatFor($user))->not->toBeNull();
});
