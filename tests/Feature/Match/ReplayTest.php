<?php

declare(strict_types=1);

use App\Actions\LiveSim\ReplayMatch;
use App\Models\LiveMatch;
use App\Models\Player;
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

/** Play a match right through, a slice at a time, as the page does. */
function runToFullTime(User $user, int $matchId): void
{
    for ($i = 0; $i < 40; $i++) {
        $body = test()->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])
            ->assertOk()
            ->json();

        if ($body['finished']) {
            break;
        }
    }
}

it('reproduces the scoreline exactly from the seed', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    runToFullTime($user, $matchId);
    $match = LiveMatch::query()->findOrFail($matchId);

    $replay = app(ReplayMatch::class)->handle($match);

    expect($replay['homeGoals'])->toBe($match->home_goals)
        ->and($replay['awayGoals'])->toBe($match->away_goals)
        ->and($replay['frames'])->not->toBeEmpty();
});

it('reproduces a match that was changed mid-flight', function () {
    $user = User::factory()->create();
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    $matchId = $props['matchId'];

    // A substitution and a mentality change both mutate engine state as the
    // match runs, so a seed on its own would not bring this match back.
    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);
    $this->actingAs($user)->postJson(route('play.mentality', $matchId), ['mentality' => 'attacking']);
    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);
    $this->actingAs($user)->postJson(route('play.sub', $matchId), [
        'out_slot' => 1,
        'player_id' => $props['bench'][0]['id'],
    ])->assertOk();

    runToFullTime($user, $matchId);
    $match = LiveMatch::query()->findOrFail($matchId);

    expect($match->interventions)->toHaveCount(2);

    $replay = app(ReplayMatch::class)->handle($match);

    expect($replay['homeGoals'])->toBe($match->home_goals)
        ->and($replay['awayGoals'])->toBe($match->away_goals);
});

it('gives the same result however many times it is replayed', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    runToFullTime($user, $matchId);
    $match = LiveMatch::query()->findOrFail($matchId);

    expect(app(ReplayMatch::class)->handle($match))
        ->toEqual(app(ReplayMatch::class)->handle($match));
});

it('leaves the stored match untouched', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    runToFullTime($user, $matchId);
    $before = LiveMatch::query()->findOrFail($matchId)->toArray();

    $this->actingAs($user)->get(route('play.replay', $matchId))->assertOk();

    expect(LiveMatch::query()->findOrFail($matchId)->toArray())->toEqual($before);
});

it('hands the whole re-simulated match to the page', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    runToFullTime($user, $matchId);

    $props = $this->actingAs($user)->get(route('play.replay', $matchId))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['replay'])->not->toBeNull()
        ->and($props['replay']['frames'])->not->toBeEmpty()
        // The commentary is the record of what was said, replayed from storage.
        ->and($props['moments'])->not->toBeEmpty();
});

it('will not replay a match that has not finished', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $this->actingAs($user)->get(route('play.replay', $matchId))->assertNotFound();
});

it('will not replay someone else\'s match', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    runToFullTime($user, $matchId);

    $this->actingAs($other)->get(route('play.replay', $matchId))->assertForbidden();
});
