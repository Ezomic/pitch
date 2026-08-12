<?php

declare(strict_types=1);

use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\User;
use App\Sim\Analysis\MatchSummary;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

function playOut(User $user, int $matchId, int $slice = 120): void
{
    // Enough rounds to reach full time at whatever slice size was asked for.
    $rounds = (int) ceil(2700 / $slice) + 2;

    for ($i = 0; $i < $rounds; $i++) {
        $body = test()->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => $slice])
            ->assertOk()
            ->json();

        if ($body['finished']) {
            break;
        }
    }
}

it('tallies a match as it is played', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    playOut($user, $matchId);
    $match = LiveMatch::query()->findOrFail($matchId);

    expect($match->summary)->not->toBeNull();

    $totals = $match->summary['teams'];

    // The goals tallied have to agree with the score the match finished on, or
    // the summary is describing a different match.
    expect($totals[0]['goals'])->toBe($match->home_goals)
        ->and($totals[1]['goals'])->toBe($match->away_goals)
        ->and($totals[0]['passes'] + $totals[1]['passes'])->toBeGreaterThan(0)
        ->and($totals[0]['frames'] + $totals[1]['frames'])->toBe($match->total_ticks);
});

it('adds up the same however the match was sliced', function () {
    $user = User::factory()->create();

    $coarse = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props']['matchId'];
    playOut($user, $coarse, 120);

    $this->actingAs($user)->post(route('play.store'));
    $fine = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props']['matchId'];

    // A friendly draws its own seed, so the two matches differ; what must hold
    // is that the tally is internally consistent whatever the slice size.
    playOut($user, $fine, 12);

    foreach ([$coarse, $fine] as $id) {
        $match = LiveMatch::query()->findOrFail($id);
        $teams = $match->summary['teams'];

        expect($teams[0]['frames'] + $teams[1]['frames'])->toBe($match->total_ticks)
            ->and($teams[0]['passesCompleted'])->toBeLessThanOrEqual($teams[0]['passes'])
            ->and($teams[0]['onTarget'])->toBeLessThanOrEqual($teams[0]['shots'])
            ->and($teams[1]['onTarget'])->toBeLessThanOrEqual($teams[1]['shots']);
    }
});

it('records every shot with where it came from and what became of it', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    playOut($user, $matchId);
    $match = LiveMatch::query()->findOrFail($matchId);

    $shots = $match->summary['shots'];
    $teams = $match->summary['teams'];

    expect($shots)->not->toBeEmpty()
        ->and(count($shots))->toBe($teams[0]['shots'] + $teams[1]['shots']);

    $goals = array_filter($shots, fn (array $s): bool => $s['outcome'] === 'goal');
    expect(count($goals))->toBe($match->home_goals + $match->away_goals);

    foreach ($shots as $shot) {
        expect($shot['outcome'])->toBeIn(['goal', 'saved', 'blocked', 'off'])
            ->and($shot['side'])->toBeIn([0, 1])
            ->and($shot['x'])->toBeGreaterThanOrEqual(0);
    }
});

it('hands the match stats to the page once there are any', function () {
    $user = User::factory()->create();

    $before = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    expect($before['summary'])->toBeNull();

    // Part way through: the match is still the one in progress, so /play resumes it.
    $this->actingAs($user)->postJson(route('play.advance', $before['matchId']), ['ticks' => 120]);

    $during = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];

    expect($during['summary'])->not->toBeNull()
        ->and($during['summary']['teams'])->toHaveCount(2)
        ->and(round($during['summary']['teams'][0]['possession'] + $during['summary']['teams'][1]['possession']))
        ->toBe(100.0);
});

it('keeps the stats on a finished match, which /play no longer resumes', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props']['matchId'];

    playOut($user, $matchId);

    // A finished match is not the one in progress, so it is read through its replay.
    $props = $this->actingAs($user)->get(route('play.replay', $matchId))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['summary'])->not->toBeNull()
        ->and($props['summary']['teams'][0]['shots'] + $props['summary']['teams'][1]['shots'])
        ->toBe(count($props['summary']['shots']));
});

it('merges slices by addition, so order cannot matter', function () {
    $summary = new MatchSummary;

    $a = MatchSummary::empty();
    $a['teams'][0]['shots'] = 3;
    $a['players'][5] = ['goals' => 1, 'shots' => 2, 'passes' => 0, 'passesCompleted' => 0,
        'crosses' => 0, 'tackles' => 0, 'interceptions' => 0, 'clearances' => 0, 'fouls' => 0, 'saves' => 0];
    $a['shots'] = [['minute' => 3, 'side' => 0, 'actor' => 5, 'x' => 4, 'y' => 2, 'outcome' => 'goal']];

    $b = MatchSummary::empty();
    $b['teams'][0]['shots'] = 2;
    $b['players'][5] = ['goals' => 0, 'shots' => 1, 'passes' => 0, 'passesCompleted' => 0,
        'crosses' => 0, 'tackles' => 0, 'interceptions' => 0, 'clearances' => 0, 'fouls' => 0, 'saves' => 0];
    $b['shots'] = [['minute' => 9, 'side' => 0, 'actor' => 5, 'x' => 5, 'y' => 2, 'outcome' => 'off']];

    $merged = $summary->merge($summary->merge(null, $a), $b);

    expect($merged['teams'][0]['shots'])->toBe(5)
        ->and($merged['players'][5]['shots'])->toBe(3)
        ->and($merged['players'][5]['goals'])->toBe(1)
        ->and($merged['shots'])->toHaveCount(2);
});
