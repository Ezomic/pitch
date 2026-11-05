<?php

declare(strict_types=1);

use App\Actions\Career\ClaimClub;
use App\Actions\Career\CreateCareer;
use App\Actions\League\CurrentRound;
use App\Actions\League\ResolveRound;
use App\Actions\League\SubmitOrder;
use App\Models\Career;
use App\Models\CareerMembership;
use App\Models\Fixture;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Models\User;
use App\Sim\Runner\MatchRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeMatchRunner;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(6)->create(['is_youth' => false]);
});

/**
 * A resolved two-manager league played by the (faked) runner.
 *
 * @return array{Career, User, User, Fixture}
 */
function replayLeague(string $engine = Career::NUTMEG, string $golden = 'win'): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $career = app(CreateCareer::class)->handle($owner, 'Sunday League', Career::LEAGUE);
    $career->forceFill(['engine' => $engine])->save();
    $career->memberships()->create(['user_id' => $member->id, 'role' => CareerMembership::MEMBER]);

    $clubs = Team::query()->where('is_youth', false)->orderBy('id')->get();
    app(ClaimClub::class)->handle($career->seatFor($owner), $clubs[0]);
    app(ClaimClub::class)->handle($career->seatFor($member), $clubs[1]);

    app()->instance(MatchRunner::class, new FakeMatchRunner($golden));

    $round = app(CurrentRound::class)->handle($career);
    app(SubmitOrder::class)->handle($round, $career->seatFor($owner), '442', 'balanced', true);
    app(SubmitOrder::class)->handle($round, $career->seatFor($member), '442', 'balanced', true);
    app(ResolveRound::class)->handle($round);

    $fixture = $round->season->fixtures()->where('youth', false)->where('matchday', 1)->orderBy('id')->firstOrFail();

    return [$career, $owner, $member, $fixture];
}

it('shows a replay whose scoreline equals the score stored on the fixture', function () {
    [$career, $owner, , $fixture] = replayLeague();

    $this->actingAs($owner)->get(route('league.replay', [$career, $fixture]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('LeagueReplay')
            ->where('replay.homeGoals', $fixture->home_goals)
            ->where('replay.awayGoals', $fixture->away_goals)
            ->where('replay.home', $fixture->homeTeam->name)
            ->where('replay.away', $fixture->awayTeam->name)
            ->where('replay.version', 'nutmeg-0.4.2')
            ->has('replay.moments', 3)
            ->where('replay.moments.0', ['minute' => 12, 'kind' => 'goal', 'text' => 'Goal for '.$fixture->homeTeam->name])
            ->where('replay.moments.1.text', 'Goal for '.$fixture->awayTeam->name)
            ->where('replay.stats.0', ['label' => 'Possession', 'home' => 54, 'away' => 46]));
});

it('replays a goalless draw', function () {
    [$career, $owner, , $fixture] = replayLeague(golden: 'draw');

    $this->actingAs($owner)->get(route('league.replay', [$career, $fixture]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('replay.homeGoals', 0)
            ->where('replay.awayGoals', 0)
            ->has('replay.moments', 0));
});

it('lets any member of the league watch, not only the owner', function () {
    [$career, , $member, $fixture] = replayLeague();

    $this->actingAs($member)->get(route('league.replay', [$career, $fixture]))->assertOk();
});

it('refuses someone who is not in the league', function () {
    [$career, , , $fixture] = replayLeague();

    $this->actingAs(User::factory()->create())
        ->get(route('league.replay', [$career, $fixture]))
        ->assertForbidden();
});

it('refuses a guest', function () {
    [$career, , , $fixture] = replayLeague();

    $this->get(route('league.replay', [$career, $fixture]))->assertRedirect(route('login'));
});

it('has no replay for a fixture the zone resolver played', function () {
    [$career, $owner, , $fixture] = replayLeague(Career::ZONE);

    expect($fixture->fresh()->played)->toBeTrue()
        ->and($fixture->fresh()->runner_events)->toBeNull();

    $this->actingAs($owner)->get(route('league.replay', [$career, $fixture]))->assertNotFound();
});

it('has no replay until the matchday is resolved', function () {
    [$career, $owner, , $fixture] = replayLeague();

    LeagueRound::query()->where('career_id', $career->id)->update(['status' => LeagueRound::PLAYING]);

    $this->actingAs($owner)->get(route('league.replay', [$career, $fixture]))->assertNotFound();
});

it('will not open another league\'s fixture through this league', function () {
    [$career, $owner] = replayLeague();
    [, , , $foreign] = replayLeague();

    $this->actingAs($owner)->get(route('league.replay', [$career, $foreign]))->assertNotFound();
});

it('links to the replay from the lobby only where one exists', function () {
    [$career, $owner, , $fixture] = replayLeague();

    $this->actingAs($owner)->get(route('league.show', $career))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feed.0.hasReplay', true)
            ->where('feed.0.fixtureId', $fixture->id));

    [$zoneCareer, $zoneOwner] = replayLeague(Career::ZONE);

    $this->actingAs($zoneOwner)->get(route('league.show', $zoneCareer))
        ->assertInertia(fn (Assert $page) => $page->where('feed.0.hasReplay', false));
});
