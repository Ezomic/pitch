<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 15, 'passing' => 15, 'dribbling' => 15, 'finishing' => 15, 'tackling' => 15, 'pace' => 15];

    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

it('renders a match report with a scoreline, moments and a positional replay stream', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('match.show', ['seed' => 3]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Match')
            ->where('seed', 3)
            ->has('report.homeGoals')
            ->has('report.awayGoals')
            ->has('report.moments')
            // The watched match is simulated positionally: 22 players and a
            // per-frame position stream the replay plays back directly.
            ->has('report.stream.players', 22)
            ->has('report.stream.frames')
            ->has('report.stream.frames.0', fn (Assert $frame) => $frame
                ->has('m')->has('b')->has('c')->has('s')->has('p')->has('cap'))
            ->has('report.stream.players.0', fn (Assert $meta) => $meta
                ->has('s')->has('slot')->has('name')->has('gk')),
        );
});

it('produces the same match for the same seed', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->get(route('match.show', ['seed' => 9]))
        ->viewData('page')['props']['report'];
    $second = $this->actingAs($user)->get(route('match.show', ['seed' => 9]))
        ->viewData('page')['props']['report'];

    expect($first['homeGoals'])->toBe($second['homeGoals'])
        ->and($first['awayGoals'])->toBe($second['awayGoals'])
        ->and(count($first['moments']))->toBe(count($second['moments']));
});

it('requires authentication', function () {
    $this->get(route('match.show'))->assertRedirect(route('login'));
});
