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

it('books a committed tackler and suspends them on the third yellow', function () {
    $enforcer = Player::factory()->create(['tackling' => 85, 'fitness' => 100, 'yellow_cards' => 2]);

    app(ApplyMatchCondition::class)->handle([$enforcer->id], 1, 0);

    $enforcer->refresh();
    expect($enforcer->suspended_weeks)->toBe(1) // third yellow triggers the ban
        ->and($enforcer->yellow_cards)->toBe(0); // reset after the ban
});

it('only bookings up committed tacklers', function () {
    $calm = Player::factory()->create(['tackling' => 50, 'fitness' => 100, 'yellow_cards' => 0]);

    app(ApplyMatchCondition::class)->handle([$calm->id], 1, 0);

    expect($calm->refresh()->yellow_cards)->toBe(0);
});

it('drops a suspended player from the XI and the pool', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 500]);
    $enforcer = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'tackling' => 90, 'fitness' => 100, 'yellow_cards' => 2]);
    $squad->assignments()->create(['player_id' => $enforcer->id, 'slot' => 1]);

    app(ApplyMatchCondition::class)->handle([$enforcer->id], 1, 0);

    expect($squad->assignments()->where('player_id', $enforcer->id)->exists())->toBeFalse()
        ->and(Player::query()->selectableFor($user->id)->pluck('id'))->not->toContain($enforcer->id);
});

it('serves the suspension down a week each week', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $player = Player::factory()->create(['user_id' => $user->id, 'suspended_weeks' => 1]);

    app(RecoverCondition::class)->handle($season);

    expect($player->refresh()->suspended_weeks)->toBe(0);
});
