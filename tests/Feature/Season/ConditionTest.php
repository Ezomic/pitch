<?php

declare(strict_types=1);

use App\Actions\Season\ApplyMatchCondition;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\RecoverCondition;
use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\EvaluateSquad;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('takes raw attributes into a match at full fitness and neutral form', function () {
    $player = Player::factory()->create([
        'fitness' => 100, 'form' => 0,
        'vision' => 10, 'passing' => 12, 'dribbling' => 14, 'finishing' => 8, 'tackling' => 11, 'pace' => 13,
    ]);

    expect($player->matchAttributes())->toEqual($player->attributes());
});

it('weakens a tired player and lifts one in form', function () {
    $tired = Player::factory()->create(['fitness' => 0, 'form' => 0, 'vision' => 10]);
    $hot = Player::factory()->create(['fitness' => 100, 'form' => 5, 'vision' => 10]);

    expect($tired->matchAttributes()->vision)->toBe(7) // 10 * 0.70
        ->and($hot->matchAttributes()->vision)->toBe(12); // 10 * 1.15 -> 11.5 -> 12
});

it('recovers fitness up to full and eases form back toward zero each week', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);

    $spent = Player::factory()->create(['user_id' => $user->id, 'fitness' => 94, 'form' => 3]);
    $cold = Player::factory()->create(['user_id' => $user->id, 'fitness' => 40, 'form' => -2]);
    $rival = Player::factory()->create(['user_id' => User::factory()->create()->id, 'fitness' => 50, 'form' => 4]);

    app(RecoverCondition::class)->handle($season);

    expect($spent->refresh()->fitness)->toBe(100) // capped, not 106
        ->and($spent->form)->toBe(2)
        ->and($cold->refresh()->fitness)->toBe(52)
        ->and($cold->form)->toBe(-1)
        ->and($rival->refresh()->fitness)->toBe(50); // another club is untouched
});

it('drains the featured XI and swings form with the result', function () {
    $winner = Player::factory()->create(['fitness' => 100, 'form' => 0]);
    $scorer = Player::factory()->create(['fitness' => 100, 'form' => 0]);
    $benched = Player::factory()->create(['fitness' => 100, 'form' => 2]);

    app(ApplyMatchCondition::class)->handle([$winner->id, $scorer->id], 2, 1, [$scorer->id]);

    expect($winner->refresh()->fitness)->toBe(82) // 100 - 18
        ->and($winner->form)->toBe(1) // won
        ->and($scorer->refresh()->form)->toBe(2) // won +1, scored +1
        ->and($benched->refresh()->fitness)->toBe(100) // did not feature
        ->and($benched->form)->toBe(2);
});

it('lowers form on a loss and clamps it to the range', function () {
    $sinking = Player::factory()->create(['form' => -5]);

    app(ApplyMatchCondition::class)->handle([$sinking->id], 0, 3);

    expect($sinking->refresh()->form)->toBe(-5); // already at the floor, clamped
});

it('makes a tired, out-of-form squad play measurably worse', function () {
    $user = User::factory()->create();
    Player::factory()->count(12)->create(); // shared senior pool

    $squad = app(EnsureSquad::class)->handle($user);
    $fresh = app(EvaluateSquad::class)->handle($squad);

    Player::query()->whereIn('id', $squad->assignments()->pluck('player_id'))
        ->update(['fitness' => 1, 'form' => -5]);

    $spent = app(EvaluateSquad::class)->handle($squad->refresh());

    expect($spent->goalsPer90)->toBeLessThan($fresh->goalsPer90)
        ->and($spent->goalsConcededPer90)->toBeGreaterThan($fresh->goalsConcededPer90);
});

it('exposes fitness and form on the squad payload', function () {
    $user = User::factory()->create();
    Player::factory()->count(12)->create();

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pool.0.fitness')
            ->has('pool.0.form'),
        );
});

it('exposes fitness and form on the academy payload', function () {
    $user = User::factory()->create();
    Player::factory()->youth($user->id)->create();

    $this->actingAs($user)
        ->get(route('youth.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('prospects.0.fitness')
            ->has('prospects.0.form'),
        );
});
