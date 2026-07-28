<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

it('starts a live positional match with 22 players', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('play.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveSim')
            ->has('matchId')
            ->has('players', 22)
            ->has('totalTicks')
            ->where('subsRemaining', 5));
});

it('advances a live match slice by slice to full time', function () {
    $user = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $finished = false;
    $goals = 0;
    for ($i = 0; $i < 40 && ! $finished; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 300])
            ->assertOk()
            ->json();
        $finished = $body['finished'];
        $goals = $body['homeGoals'] + $body['awayGoals'];
    }

    expect($finished)->toBeTrue()
        ->and($goals)->toBeGreaterThanOrEqual(0);
});

it('changes mentality mid-match', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $this->actingAs($user)
        ->postJson(route('play.mentality', $matchId), ['mentality' => 'attacking'])
        ->assertOk()
        ->assertJson(['mentality' => 'attacking']);
});
