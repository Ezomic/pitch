<?php

declare(strict_types=1);

use App\Actions\Season\ApplyMatchCondition;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('doubles the form swing at raised stakes', function () {
    $winner = Player::factory()->create(['form' => 0, 'fitness' => 100]);

    app(ApplyMatchCondition::class)->handle([$winner->id], 2, 0, [], 2);

    expect($winner->refresh()->form)->toBe(2); // win (+1) doubled by derby stakes
});

it('marks derby fixtures on the season page', function () {
    Player::factory()->count(8)->create(['position' => Position::Defender]);
    Player::factory()->count(8)->create(['position' => Position::Midfielder]);
    Player::factory()->count(8)->create(['position' => Position::Forward]);
    Team::factory()->create(['is_youth' => false, 'is_derby' => true]);
    Team::factory()->count(6)->create(['is_youth' => false]);
    Team::factory()->count(5)->create(['is_youth' => true]);

    $user = User::factory()->create();
    app(EnsureSquad::class)->handle($user);
    app(EnsureSeason::class)->handle($user);

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('matchdays', fn ($days) => collect($days)
                ->flatMap(fn ($d) => $d['fixtures'])
                ->contains(fn ($f) => $f['isDerby'] === true)),
        );
});
