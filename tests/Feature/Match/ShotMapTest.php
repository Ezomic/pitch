<?php

declare(strict_types=1);

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

it('gives the page shots it can actually draw', function () {
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

    $shots = $props['summary']['shots'];
    expect($shots)->not->toBeEmpty();

    foreach ($shots as $shot) {
        // The map places a marker from these four, so all four have to be there
        // and inside the zone grid, or a shot lands off the pitch.
        expect($shot['x'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(5)
            ->and($shot['y'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(4)
            ->and($shot['side'])->toBeIn([0, 1])
            ->and($shot['outcome'])->toBeIn(['goal', 'saved', 'blocked', 'off']);
    }
});

it('records shots from advanced positions, which is what makes a map worth drawing', function () {
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

    $shots = $this->actingAs($user)->get(route('play.replay', $matchId))
        ->viewData('page')['props']['summary']['shots'];

    // The zone is measured up the shooting side's own attacking axis, so a shot
    // sits near the top of the range whichever end it was taken at. A map that
    // mirrored the away side wrongly would show them shooting at their own goal.
    $advanced = array_filter($shots, fn (array $s): bool => $s['x'] >= 4);
    expect(count($advanced))->toBeGreaterThan(count($shots) / 2);

    foreach ([0, 1] as $side) {
        $forSide = array_filter($shots, fn (array $s): bool => $s['side'] === $side);

        if ($forSide !== []) {
            $mean = array_sum(array_column($forSide, 'x')) / count($forSide);
            expect($mean)->toBeGreaterThan(3.0);
        }
    }
});
