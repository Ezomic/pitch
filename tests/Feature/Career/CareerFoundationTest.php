<?php

declare(strict_types=1);

use App\Models\Career;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('lets a manager hold several independent careers', function () {
    $user = User::factory()->create();

    $solo = Career::factory()->for($user)->create(['name' => 'Solo run']);
    $league = Career::factory()->for($user)->league()->create(['name' => 'Friends league']);

    expect($user->careers()->count())->toBe(2)
        ->and($solo->isLeague())->toBeFalse()
        ->and($league->isLeague())->toBeTrue()
        ->and($league->type)->toBe(Career::LEAGUE);
});

it('scopes every game table to a career', function () {
    foreach (['squads', 'seasons', 'players', 'scouts', 'news', 'live_matches'] as $table) {
        expect(Schema::hasColumn($table, 'career_id'))->toBeTrue("{$table} should carry career_id");
    }
});

it('keeps a career and its game state together, and cleans up with it', function () {
    $user = User::factory()->create();
    $career = Career::factory()->for($user)->create();

    $squad = Squad::query()->create(['user_id' => $user->id, 'career_id' => $career->id, 'name' => 'Test FC']);

    expect($career->squads()->pluck('id')->all())->toBe([$squad->id]);

    $career->delete();

    expect(Squad::query()->whereKey($squad->id)->exists())->toBeFalse();
});
