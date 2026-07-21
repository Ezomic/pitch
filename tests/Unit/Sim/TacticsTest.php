<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;

function setupOf(Formation $formation, Mentality $mentality): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes(60, 60, 60, 60, 60, 60);
    }

    return new TeamSetup($bySlot, $formation, $mentality);
}

it('defines valid formations of ten players, at most five per column', function () {
    $ids = [];
    foreach (Formation::all() as $id => $formation) {
        $ids[] = $id;

        expect($formation->layout)->toHaveCount(10)
            ->and(array_keys($formation->layout))->toBe(range(1, 10));

        $perColumn = [];
        foreach ($formation->layout as [$zone]) {
            $perColumn[$zone->x] = ($perColumn[$zone->x] ?? 0) + 1;
        }
        expect(max($perColumn))->toBeLessThanOrEqual(5);
    }

    expect($ids)->toBe(array_unique($ids));
});

it('orders the mentality biases', function () {
    expect(Mentality::Attacking->attackBias())->toBeGreaterThan(Mentality::Balanced->attackBias())
        ->and(Mentality::Balanced->attackBias())->toBeGreaterThan(Mentality::Defensive->attackBias())
        ->and(Mentality::Defensive->defenceBias())->toBeGreaterThan(Mentality::Balanced->defenceBias())
        ->and(Mentality::Balanced->defenceBias())->toBeGreaterThan(Mentality::Attacking->defenceBias());
});

it('makes an attacking setup create and concede more than a defensive one', function () {
    $evaluator = new SquadEvaluator;
    $baseline = TeamSetup::baseline();

    $attacking = $evaluator->evaluate(setupOf(Formation::attacking(), Mentality::Attacking), $baseline, 150);
    $defensive = $evaluator->evaluate(setupOf(Formation::defensive(), Mentality::Defensive), $baseline, 150);

    expect($attacking->chancesPer90)->toBeGreaterThan($defensive->chancesPer90)
        ->and($attacking->chancesConcededPer90)->toBeGreaterThan($defensive->chancesConcededPer90);
});

it('concedes fewer chances with a defensive setup than a balanced one', function () {
    $evaluator = new SquadEvaluator;
    $baseline = TeamSetup::baseline();

    $defensive = $evaluator->evaluate(setupOf(Formation::defensive(), Mentality::Defensive), $baseline, 150);
    $balanced = $evaluator->evaluate(setupOf(Formation::balanced(), Mentality::Balanced), $baseline, 150);

    expect($defensive->chancesConcededPer90)->toBeLessThan($balanced->chancesConcededPer90);
});
