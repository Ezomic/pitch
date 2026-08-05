<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

function dueFixtureFor(User $user, bool $userIsHome = true): Fixture
{
    $season = Season::create([
        'user_id' => $user->id,
        'number' => 1,
        'starts_on' => Season::STARTS_ON,
        'current_date' => Season::STARTS_ON,
    ]);
    $team = Team::factory()->create(['is_youth' => false]);

    return $season->fixtures()->create([
        'matchday' => 1,
        'scheduled_on' => Season::STARTS_ON,
        'home_team_id' => $userIsHome ? null : $team->id,
        'away_team_id' => $userIsHome ? $team->id : null,
        'seed' => 4242,
        'played' => false,
    ]);
}

/** Play the match right through, a slice at a time, as the page does. */
function playToFullTime(User $user, int $matchId): array
{
    $body = [];
    for ($i = 0; $i < 40; $i++) {
        $body = test()->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])
            ->assertOk()
            ->json();

        if ($body['finished']) {
            break;
        }
    }

    return $body;
}

it('plays the due league fixture rather than a friendly', function () {
    $user = User::factory()->create();
    $fixture = dueFixtureFor($user);

    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];

    $match = LiveMatch::query()->findOrFail($props['matchId']);

    expect($props['competitive'])->toBeTrue()
        ->and($match->fixture_id)->toBe($fixture->id)
        // Seeded from the fixture, so the match is reproducible from stored state
        // rather than from a random_int nobody recorded.
        ->and($match->seed)->toBe($fixture->seed);
});

it('writes the played-out score back onto the fixture at full time', function () {
    $user = User::factory()->create();
    $fixture = dueFixtureFor($user);

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $body = playToFullTime($user, $matchId);

    $fixture->refresh();

    expect($body['finished'])->toBeTrue()
        ->and($fixture->played)->toBeTrue()
        ->and($fixture->home_goals)->toBe($body['homeGoals'])
        ->and($fixture->away_goals)->toBe($body['awayGoals']);
});

it('maps the score to the fixture orientation when the user is away', function () {
    $user = User::factory()->create();
    $fixture = dueFixtureFor($user, userIsHome: false);

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $body = playToFullTime($user, $matchId);

    $fixture->refresh();

    // The engine always runs the manager as side 0, so an away fixture has to
    // come out the other way round on the fixture itself.
    expect($fixture->home_goals)->toBe($body['awayGoals'])
        ->and($fixture->away_goals)->toBe($body['homeGoals']);
});

it('files the result into the news feed', function () {
    $user = User::factory()->create();
    dueFixtureFor($user);

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    playToFullTime($user, $matchId);

    expect(News::query()->where('user_id', $user->id)->where('category', News::RESULT)->count())->toBe(1);
});

it('settles a fixture only once, however many times the last slice is asked for', function () {
    $user = User::factory()->create();
    $fixture = dueFixtureFor($user);

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    playToFullTime($user, $matchId);
    $settled = $fixture->fresh();

    // Asking again after full time must not re-record the result.
    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120])->assertOk();

    expect($fixture->fresh()->home_goals)->toBe($settled->home_goals)
        ->and(News::query()->where('user_id', $user->id)->where('category', News::RESULT)->count())->toBe(1);
});

it('falls back to a friendly when no league fixture is due', function () {
    $user = User::factory()->create();

    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];

    expect($props['competitive'])->toBeFalse()
        ->and(LiveMatch::query()->findOrFail($props['matchId'])->fixture_id)->toBeNull();
});

it('sends the season page to the live match screen', function () {
    $user = User::factory()->create();
    dueFixtureFor($user);

    $this->actingAs($user)->get(route('season.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('liveFixture.url', route('play.show')));
});
