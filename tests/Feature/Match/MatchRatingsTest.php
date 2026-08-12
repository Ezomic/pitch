<?php

declare(strict_types=1);

use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\User;
use App\Sim\Analysis\MatchRatings;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

/**
 * @param  array<string, int>  $counters
 * @return array<string, mixed>
 */
function summaryOf(array $counters): array
{
    $base = ['ticks' => 2700, 'goals' => 0, 'shots' => 0, 'passes' => 0, 'passesCompleted' => 0,
        'crosses' => 0, 'tackles' => 0, 'interceptions' => 0, 'clearances' => 0, 'fouls' => 0, 'saves' => 0];

    return ['teams' => [], 'players' => [1 => [...$base, ...$counters]], 'shots' => []];
}

it('rates a quiet game at par and a goal above it', function () {
    $ratings = new MatchRatings;

    $quiet = $ratings->forMatch(summaryOf([]))[1];
    $scorer = $ratings->forMatch(summaryOf(['goals' => 1, 'shots' => 2]))[1];

    expect($quiet)->toBe(6.0)
        ->and($scorer)->toBeGreaterThan($quiet);
});

it('does not read anything into a handful of passes', function () {
    $ratings = new MatchRatings;

    // Two passes, both astray, is not evidence of a bad game.
    $thin = $ratings->forMatch(summaryOf(['passes' => 2, 'passesCompleted' => 0]))[1];

    // Forty passes at a third completion is.
    $poor = $ratings->forMatch(summaryOf(['passes' => 40, 'passesCompleted' => 13]))[1];

    expect($thin)->toBe(6.0)->and($poor)->toBeLessThan(6.0);
});

it('marks a player down for conceding fouls', function () {
    $ratings = new MatchRatings;

    expect($ratings->forMatch(summaryOf(['fouls' => 4]))[1])->toBeLessThan(6.0);
});

it('keeps every rating between 1 and 10', function () {
    $ratings = new MatchRatings;

    $huge = $ratings->forMatch(summaryOf(['goals' => 9, 'saves' => 30, 'tackles' => 40]))[1];
    $awful = $ratings->forMatch(summaryOf(['fouls' => 60, 'passes' => 40, 'passesCompleted' => 0]))[1];

    expect($huge)->toBeLessThanOrEqual(10.0)->and($awful)->toBeGreaterThanOrEqual(1.0);
});

it('rates the manager players after a real match, and names one of them', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    for ($i = 0; $i < 40; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])->json();

        if ($body['finished']) {
            break;
        }
    }

    $props = $this->actingAs($user)->get(route('play.replay', $matchId))
        ->assertOk()->viewData('page')['props'];

    expect($props['ratings'])->not->toBeNull()
        ->and($props['ratings'])->not->toBeEmpty();

    $motm = array_filter($props['ratings'], fn (array $r): bool => $r['motm']);
    expect($motm)->toHaveCount(1);

    // Best first, and nobody outside the scale.
    $values = array_column($props['ratings'], 'rating');
    expect($values)->toBe(array_reverse(collect($values)->sort()->values()->all()));

    foreach ($values as $rating) {
        expect($rating)->toBeGreaterThanOrEqual(1.0)->toBeLessThanOrEqual(10.0);
    }
});

it('credits a substitute with his own work, not the man he replaced', function () {
    $user = User::factory()->create();
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    $matchId = $props['matchId'];

    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);
    $this->actingAs($user)->postJson(route('play.sub', $matchId), [
        'out_slot' => 1, 'player_id' => $props['bench'][0]['id'],
    ])->assertOk();

    for ($i = 0; $i < 40; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])->json();

        if ($body['finished']) {
            break;
        }
    }

    $match = LiveMatch::query()->findOrFail($matchId);
    $players = $match->summary['players'];

    // Both men appear in their own right. Keyed by shirt, the substitute's work
    // would have been credited to the player he came on for.
    $starter = collect($props['onPitch'])->firstWhere('slot', 1);
    expect($starter)->not->toBeNull();

    $sub = $props['bench'][0]['id'];
    expect($players)->toHaveKey((string) $sub);

    // He was on for less of it than a man who played the whole match.
    expect($players[$sub]['ticks'])->toBeLessThan($match->total_ticks)
        ->and($players[$sub]['ticks'])->toBeGreaterThan(0);
});
