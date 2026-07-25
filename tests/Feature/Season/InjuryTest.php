<?php

declare(strict_types=1);

use App\Actions\Season\ApplyMatchCondition;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\RecoverCondition;
use App\Models\Player;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(7)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);
});

it('breaks down an exhausted player and drops them from the XI', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 500]);
    $player = Player::factory()->create(['fitness' => 10, 'injured_weeks' => 0]); // will drain to 0
    $squad->assignments()->create(['player_id' => $player->id, 'slot' => 1]);

    app(ApplyMatchCondition::class)->handle([$player->id], 1, 0);

    $player->refresh();
    expect($player->injured_weeks)->toBeGreaterThan(0) // exhaustion injury
        ->and($squad->assignments()->where('player_id', $player->id)->exists())->toBeFalse(); // dropped
});

it('excludes an injured player from the selectable pool', function () {
    $user = User::factory()->create();
    Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'injured_weeks' => 2]);
    $fit = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'injured_weeks' => 0]);

    $ids = Player::query()->selectableFor($user->id)->pluck('id');

    expect($ids)->toContain($fit->id)
        ->and($ids)->toHaveCount(1);
});

it('heals an injury by a week each week of rest', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $player = Player::factory()->create(['user_id' => $user->id, 'injured_weeks' => 2]);

    app(RecoverCondition::class)->handle($season);

    expect($player->refresh()->injured_weeks)->toBe(1);
});
