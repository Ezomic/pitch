<?php

declare(strict_types=1);

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\EvaluateSquad;
use App\Models\Player;
use App\Models\User;
use App\Sim\Engine\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function squadWithSquad(): array
{
    $user = User::factory()->create();
    Player::factory()->count(16)->create(['is_youth' => false]);
    $squad = app(EnsureSquad::class)->handle($user);

    return [$user, $squad];
}

/** @return array<int, array{slot: int, x: int, y: int}> */
function tenPlacements(int $forwardY = 2): array
{
    $rows = [[1, 1], [1, 3], [2, 0], [2, 2], [2, 4], [3, 1], [3, 3], [4, 0], [5, $forwardY], [4, 4]];
    $placements = [];
    foreach ($rows as $index => [$x, $y]) {
        $placements[] = ['slot' => $index + 1, 'x' => $x, 'y' => $y];
    }

    return $placements;
}

it('stores a custom formation and plays it', function () {
    [$user, $squad] = squadWithSquad();

    $this->actingAs($user)
        ->patch(route('squad.customize'), ['placements' => tenPlacements()])
        ->assertRedirect(route('squad.edit'));

    $squad->refresh();
    expect($squad->formation)->toBe(Formation::CUSTOM_ID)
        ->and($squad->custom_formation)->toHaveCount(10)
        ->and($squad->formationObject()->id)->toBe(Formation::CUSTOM_ID)
        ->and($squad->formationObject()->layout[9][0]->x)->toBe(5);
});

it('seeds the custom layout from the current preset when switching to custom', function () {
    [$user, $squad] = squadWithSquad();
    $squad->forceFill(['formation' => '442'])->save();

    $this->actingAs($user)
        ->patch(route('squad.tactics'), ['formation' => Formation::CUSTOM_ID, 'mentality' => 'balanced'])
        ->assertRedirect(route('squad.edit'));

    $squad->refresh();
    expect($squad->formation)->toBe(Formation::CUSTOM_ID)
        ->and($squad->custom_formation)->toEqual(Formation::preset442()->placements());
});

it('rejects a custom formation that is not a full eleven of outfielders', function () {
    [$user] = squadWithSquad();

    $this->actingAs($user)
        ->patch(route('squad.customize'), ['placements' => array_slice(tenPlacements(), 0, 8)])
        ->assertSessionHasErrors('placements');
});

it('rejects placements outside the pitch grid', function () {
    [$user] = squadWithSquad();
    $placements = tenPlacements();
    $placements[0]['x'] = 9;

    $this->actingAs($user)
        ->patch(route('squad.customize'), ['placements' => $placements])
        ->assertSessionHasErrors('placements.0.x');
});

it('reshapes the profile when the custom shape changes', function () {
    [$user, $squad] = squadWithSquad();

    $defensive = tenPlacements(forwardY: 2);
    // Push the whole side forward to a far more attacking shape.
    $attacking = array_map(fn (array $p) => [...$p, 'x' => min(5, $p['x'] + 1)], $defensive);

    $squad->forceFill(['formation' => Formation::CUSTOM_ID, 'custom_formation' => collect($defensive)->mapWithKeys(fn ($p) => [$p['slot'] => [$p['x'], $p['y']]])->all()])->save();
    $before = app(EvaluateSquad::class)->handle($squad->fresh());

    $squad->forceFill(['custom_formation' => collect($attacking)->mapWithKeys(fn ($p) => [$p['slot'] => [$p['x'], $p['y']]])->all()])->save();
    $after = app(EvaluateSquad::class)->handle($squad->fresh());

    expect($after->chancesPer90)->not->toBe($before->chancesPer90);
});

it('offers a custom option and flags a custom squad on the squad page', function () {
    [$user, $squad] = squadWithSquad();
    $squad->forceFill(['formation' => Formation::CUSTOM_ID, 'custom_formation' => collect(tenPlacements())->mapWithKeys(fn ($p) => [$p['slot'] => [$p['x'], $p['y']]])->all()])->save();

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Squad')
            ->where('squad.isCustom', true)
            ->where('formations', fn ($formations) => collect($formations)->contains('id', Formation::CUSTOM_ID)),
        );
});
