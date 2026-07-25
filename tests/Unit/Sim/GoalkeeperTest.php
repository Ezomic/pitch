<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;

function sideWithKeeper(int $rating, int $goalkeeping): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes($rating, $rating, $rating, $rating, $rating, $rating);
    }

    return new TeamSetup($bySlot, Formation::balanced(), Mentality::Balanced, $goalkeeping);
}

it('concedes fewer goals with a better keeper, without changing chances conceded', function () {
    $evaluator = new SquadEvaluator;
    $opponent = TeamSetup::baseline();

    $weakKeeper = $evaluator->evaluate(sideWithKeeper(60, 30), $opponent, 120);
    $strongKeeper = $evaluator->evaluate(sideWithKeeper(60, 90), $opponent, 120);

    expect($strongKeeper->goalsConcededPer90)->toBeLessThan($weakKeeper->goalsConcededPer90)
        ->and($strongKeeper->chancesConcededPer90)->toBe($weakKeeper->chancesConcededPer90);
});

it('is deterministic for a fixed keeper rating', function () {
    $evaluator = new SquadEvaluator;
    $opponent = TeamSetup::baseline();

    $a = $evaluator->evaluate(sideWithKeeper(60, 75), $opponent, 60);
    $b = $evaluator->evaluate(sideWithKeeper(60, 75), $opponent, 60);

    expect($a->goalsConcededPer90)->toBe($b->goalsConcededPer90);
});
