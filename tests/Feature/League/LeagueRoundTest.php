<?php

declare(strict_types=1);

use App\Actions\Career\ClaimClub;
use App\Actions\Career\CreateCareer;
use App\Actions\Career\InviteToLeague;
use App\Actions\League\CurrentRound;
use App\Actions\League\EnsureLeagueSeason;
use App\Actions\League\ResolveRound;
use App\Actions\League\SubmitOrder;
use App\Models\Career;
use App\Models\CareerMembership;
use App\Models\Fixture;
use App\Models\LeagueOrder;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(6)->create(['is_youth' => false]);
});

/**
 * A league with $managers seated, each running a club.
 *
 * @return array{Career, list<User>}
 */
function leagueWithClubs(int $managers = 2): array
{
    $users = [];
    $owner = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Sunday League', Career::LEAGUE);
    $users[] = $owner;

    for ($i = 1; $i < $managers; $i++) {
        $joiner = User::factory()->create();
        $invitation = app(InviteToLeague::class)->handle($career, $owner);
        $career->memberships()->create([
            'user_id' => $joiner->id,
            'role' => CareerMembership::MEMBER,
        ]);
        $invitation->claim($joiner);
        $users[] = $joiner;
    }

    $clubs = Team::query()->where('is_youth', false)->orderBy('id')->get();

    foreach ($users as $index => $user) {
        app(ClaimClub::class)->handle($career->seatFor($user), $clubs[$index]);
    }

    return [$career, $users];
}

function readyUp(Career $career, User $user, string $formation = '442', string $mentality = 'balanced'): ?LeagueOrder
{
    $round = app(CurrentRound::class)->handle($career);

    return app(SubmitOrder::class)->handle($round, $career->seatFor($user), $formation, $mentality, true);
}

it('gives a league one shared season every member reads', function () {
    [$career] = leagueWithClubs(2);

    $first = app(EnsureLeagueSeason::class)->handle($career);
    $second = app(EnsureLeagueSeason::class)->handle($career);

    expect($second->id)->toBe($first->id)
        ->and($career->seasons()->count())->toBe(1)
        // Nobody is the null side: a league is played between real clubs.
        ->and($first->fixtures()->whereNull('home_team_id')->count())->toBe(0)
        ->and($first->fixtures()->whereNull('away_team_id')->count())->toBe(0)
        ->and($first->fixtures()->where('youth', false)->count())->toBe(6 * 5);
});

it('opens the round on the next unplayed matchday, with a deadline', function () {
    [$career] = leagueWithClubs(2);

    $round = app(CurrentRound::class)->handle($career);

    expect($round)->toBeInstanceOf(LeagueRound::class)
        ->and($round->matchday)->toBe(1)
        ->and($round->isOpen())->toBeTrue()
        ->and($round->deadline_at)->not->toBeNull()
        // Asking again does not keep opening rounds.
        ->and(app(CurrentRound::class)->handle($career)->id)->toBe($round->id);
});

it('waits while a manager has not handed in a team sheet', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    $round = app(CurrentRound::class)->handle($career);

    expect(app(ResolveRound::class)->handle($round))->toBeFalse()
        ->and($round->fresh()->isOpen())->toBeTrue()
        ->and($round->season->fixtures()->where('played', true)->count())->toBe(0);
});

it('plays the matchday once everyone is ready', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    readyUp($career, $users[1]);

    $round = app(CurrentRound::class)->handle($career);

    expect(app(ResolveRound::class)->handle($round))->toBeTrue()
        ->and($round->fresh()->status)->toBe(LeagueRound::RESOLVED)
        ->and($round->fresh()->resolved_at)->not->toBeNull();

    $played = $round->season->fixtures()->where('youth', false)->where('matchday', 1)->get();

    expect($played)->not->toBeEmpty()
        ->and($played->every(fn (Fixture $f): bool => $f->played))->toBeTrue();
});

it('moves on to the next matchday once one is played', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    readyUp($career, $users[1]);
    app(ResolveRound::class)->handle(app(CurrentRound::class)->handle($career));

    expect(app(CurrentRound::class)->handle($career)->matchday)->toBe(2);
});

it('plays without a manager once the deadline runs out, and says so', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    $round = app(CurrentRound::class)->handle($career);
    $round->forceFill(['deadline_at' => Date::now()->subMinute()])->save();

    expect(app(ResolveRound::class)->handle($round))->toBeTrue();

    $absent = $career->seatFor($users[1]);
    $order = $round->orderFor($absent);

    expect($order)->toBeInstanceOf(LeagueOrder::class)
        ->and($order->auto)->toBeTrue()
        ->and($order->ready)->toBeTrue()
        // Nobody told them anything, so the club plays the way it always plays.
        ->and($order->formation)->toBe($absent->team->formation);
});

it('keeps the side a manager named even when they never marked it ready', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);

    $round = app(CurrentRound::class)->handle($career);
    app(SubmitOrder::class)->handle($round, $career->seatFor($users[1]), '532', 'defensive', false);
    $round->forceFill(['deadline_at' => Date::now()->subMinute()])->save();

    app(ResolveRound::class)->handle($round);

    $order = $round->orderFor($career->seatFor($users[1]));

    expect($order->formation)->toBe('532')
        ->and($order->mentality)->toBe('defensive')
        ->and($order->auto)->toBeTrue();
});

it('will not run a deadline down on a league nobody has claimed a club in', function () {
    $owner = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Empty', Career::LEAGUE);

    $round = app(CurrentRound::class)->handle($career);
    $round->forceFill(['deadline_at' => Date::now()->subDay()])->save();

    expect(app(ResolveRound::class)->handle($round))->toBeFalse()
        ->and($round->season->fixtures()->where('played', true)->count())->toBe(0);
});

it('gives the same result for the same orders and seed', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0], '433', 'attacking');
    readyUp($career, $users[1], '532', 'defensive');
    $round = app(CurrentRound::class)->handle($career);
    app(ResolveRound::class)->handle($round);

    $first = $round->season->fixtures()->where('matchday', 1)->where('youth', false)
        ->get()->map(fn (Fixture $f): string => "{$f->seed}:{$f->home_goals}-{$f->away_goals}")->all();

    // Same league, same orders, same seeds: replaying it must land in the same place.
    $round->season->fixtures()->where('matchday', 1)->update([
        'played' => false, 'home_goals' => null, 'away_goals' => null,
    ]);
    $round->forceFill(['status' => LeagueRound::OPEN, 'resolved_at' => null])->save();
    app(ResolveRound::class)->handle($round->fresh());

    $second = $round->season->fixtures()->where('matchday', 1)->where('youth', false)
        ->get()->map(fn (Fixture $f): string => "{$f->seed}:{$f->home_goals}-{$f->away_goals}")->all();

    expect($second)->toBe($first);
});

it('lets a manager change their mind while the round is open', function () {
    [$career, $users] = leagueWithClubs(2);
    $round = app(CurrentRound::class)->handle($career);
    $seat = $career->seatFor($users[0]);

    app(SubmitOrder::class)->handle($round, $seat, '433', 'attacking', true);
    app(SubmitOrder::class)->handle($round, $seat, '532', 'defensive', false);

    expect($round->orders()->count())->toBe(1)
        ->and($round->orderFor($seat)->formation)->toBe('532')
        ->and($round->orderFor($seat)->ready)->toBeFalse();
});

it('takes no orders for a manager without a club', function () {
    [$career] = leagueWithClubs(1);
    $clubless = User::factory()->create();
    $seat = $career->memberships()->create([
        'user_id' => $clubless->id, 'role' => CareerMembership::MEMBER,
    ]);

    $round = app(CurrentRound::class)->handle($career);

    expect(app(SubmitOrder::class)->handle($round, $seat, '442', 'balanced', true))->toBeNull()
        ->and($round->orders()->count())->toBe(0);
});

it('shows the open round in the lobby, and who it is waiting on', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);

    $this->actingAs($users[0])->get(route('league.show', $career))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('round.matchday', 1)
            ->where('round.yourOrder.ready', true)
            ->where('round.waitingOn', [$users[1]->name])
            ->has('round.fixtures', 3)
            ->has('formations')
            ->has('mentalities'));
});

it('plays the round when the last manager submits from the lobby', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);

    $this->actingAs($users[1])->post(route('league.order', $career), [
        'formation' => '442', 'mentality' => 'balanced', 'ready' => true,
    ])->assertRedirect(route('league.show', $career));

    expect($career->rounds()->where('matchday', 1)->first()->status)->toBe(LeagueRound::RESOLVED);
});

it('refuses a shape the engine does not know', function () {
    [$career, $users] = leagueWithClubs(2);

    $this->actingAs($users[0])->post(route('league.order', $career), [
        'formation' => '1234', 'mentality' => 'balanced', 'ready' => true,
    ])->assertSessionHasErrors('formation');
});

it('lets only the owner change how long the league waits', function () {
    [$career, $users] = leagueWithClubs(2);

    $this->actingAs($users[1])->post(route('league.cadence', $career), ['hours' => 6])->assertForbidden();

    $this->actingAs($users[0])->post(route('league.cadence', $career), ['hours' => 6])
        ->assertRedirect(route('league.show', $career));

    expect($career->fresh()->round_hours)->toBe(6);
});

it('resolves a round whose deadline passed the next time the lobby is opened', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    $round = app(CurrentRound::class)->handle($career);
    $round->forceFill(['deadline_at' => Date::now()->subMinute()])->save();

    $this->actingAs($users[1])->get(route('league.show', $career))
        ->assertInertia(fn (Assert $page) => $page->where('round.matchday', 2));

    expect($round->fresh()->status)->toBe(LeagueRound::RESOLVED);
});

it('plays a round whose deadline passed with nobody watching', function () {
    [$career, $users] = leagueWithClubs(2);

    readyUp($career, $users[0]);
    $round = app(CurrentRound::class)->handle($career);
    $round->forceFill(['deadline_at' => Date::now()->subMinute()])->save();

    $this->artisan('pitch:resolve-league-rounds')->assertSuccessful();

    expect($round->fresh()->status)->toBe(LeagueRound::RESOLVED);
});

it('leaves a solo save alone', function () {
    $user = User::factory()->create();
    $solo = app(CreateCareer::class)->handle($user, 'Just me');

    $this->artisan('pitch:resolve-league-rounds')->assertSuccessful();

    expect($solo->seasons()->count())->toBe(0);
});
