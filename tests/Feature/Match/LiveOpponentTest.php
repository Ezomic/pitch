<?php

declare(strict_types=1);

use App\Actions\LiveSim\StartMatch;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use App\Sim\Pitch\PitchState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

/**
 * The away side's outfield anchors, which encode the shape it lines up in.
 *
 * @param  array<string, mixed>  $snapshot
 */
function awayShape(array $snapshot): string
{
    $anchors = [];
    foreach ($snapshot['players'] as $player) {
        if ($player['side'] === 1 && $player['slot'] !== 0) {
            $anchors[] = implode(',', array_map(fn (float $c): string => number_format($c, 3), $player['anchor']));
        }
    }
    sort($anchors);

    return implode('|', $anchors);
}

it('plays real clubs, not one hardcoded sparring partner', function () {
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);

    Team::factory()->create(['name' => 'Ironside FC', 'is_youth' => false, 'division' => $squad->division, 'formation' => '442']);

    $match = app(StartMatch::class)->handle($user, $squad);

    expect($match->away_name)->toBe('Ironside FC');
});

it('varies the opponent shape across matches', function () {
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);

    foreach (['442', '433', '352', '532'] as $formation) {
        Team::factory()->create([
            'is_youth' => false,
            'division' => $squad->division,
            'formation' => $formation,
        ]);
    }

    $shapes = [];
    foreach (range(1, 25) as $ignored) {
        $match = app(StartMatch::class)->handle($user, $squad);
        $shapes[awayShape($match->pitch_state)] = true;
    }

    // Different clubs line up differently, so a run of matches must not all be
    // played against the identical shape.
    expect(count($shapes))->toBeGreaterThan(1);
});

it('rebuilds the same opponent when a match resumes', function () {
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);
    Team::factory()->create(['is_youth' => false, 'division' => $squad->division, 'formation' => '352']);

    $match = app(StartMatch::class)->handle($user, $squad);
    $restored = PitchState::fromSnapshot($match->pitch_state);

    expect(awayShape($restored->toSnapshot()))->toBe(awayShape($match->pitch_state));
});
