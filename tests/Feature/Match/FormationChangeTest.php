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

function liveMatchFor(User $user): int
{
    return test()->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];
}

function runOut(User $user, int $matchId): void
{
    for ($i = 0; $i < 40; $i++) {
        $body = test()->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])->json();

        if ($body['finished']) {
            break;
        }
    }
}

it('moves the side into a new shape without teleporting anyone', function () {
    $user = User::factory()->create();
    $matchId = liveMatchFor($user);

    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);

    $before = LiveMatch::query()->findOrFail($matchId)->pitch_state['players'];

    $this->actingAs($user)
        ->postJson(route('play.formation', $matchId), ['formation' => '532'])
        ->assertOk()
        ->assertJson(['formation' => '532']);

    $after = LiveMatch::query()->findOrFail($matchId)->pitch_state['players'];

    $anchorsMoved = 0;
    foreach ($before as $i => $player) {
        if ((int) $player['side'] !== 0 || (int) $player['slot'] === 0) {
            continue;
        }

        if ($player['anchor'] !== $after[$i]['anchor']) {
            $anchorsMoved++;
        }

        // An anchor is where a player wants to be, not where he is: nobody is
        // picked up and dropped somewhere else mid-match.
        expect($after[$i]['p'])->toBe($player['p']);
    }

    expect($anchorsMoved)->toBeGreaterThan(0);
});

it('records the change so a replay reproduces the match exactly', function () {
    $user = User::factory()->create();
    $matchId = liveMatchFor($user);

    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);
    $this->actingAs($user)->postJson(route('play.formation', $matchId), ['formation' => '442'])->assertOk();
    runOut($user, $matchId);

    $match = LiveMatch::query()->findOrFail($matchId);
    $shapes = array_filter($match->interventions, fn (array $i): bool => $i['type'] === 'formation');

    expect($shapes)->toHaveCount(1);

    $replay = app(ReplayMatch::class)->handle($match);

    // Without reapplying the shape at the right tick the replay would drift.
    expect($replay['homeGoals'])->toBe($match->home_goals)
        ->and($replay['awayGoals'])->toBe($match->away_goals);
});

it('remembers the shape across a page load', function () {
    $user = User::factory()->create();
    $matchId = liveMatchFor($user);

    $this->actingAs($user)->postJson(route('play.formation', $matchId), ['formation' => '343'])->assertOk();

    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];

    expect($props['formation'])->toBe('343')
        ->and($props['formations'])->not->toBeEmpty();
});

it('rejects a shape that is not one of the presets', function () {
    $user = User::factory()->create();
    $matchId = liveMatchFor($user);

    $this->actingAs($user)
        ->postJson(route('play.formation', $matchId), ['formation' => 'catenaccio'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('formation');

    $shapes = array_filter(
        LiveMatch::query()->findOrFail($matchId)->interventions ?? [],
        fn (array $i): bool => $i['type'] === 'formation',
    );

    expect($shapes)->toBeEmpty();
});

it('does not let someone else reshape your side', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $matchId = liveMatchFor($user);

    $this->actingAs($other)
        ->postJson(route('play.formation', $matchId), ['formation' => '442'])
        ->assertForbidden();
});
