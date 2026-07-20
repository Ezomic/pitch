<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;

function squadOf(int $vision): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes($vision, 12, 12, 12, 12, 12);
    }

    return new TeamSetup($bySlot, Formation::balanced(), Mentality::Balanced);
}

it('evaluates a squad deterministically', function () {
    $evaluator = new SquadEvaluator;

    $a = $evaluator->evaluate(squadOf(10), TeamSetup::baseline(), 60);
    $b = $evaluator->evaluate(squadOf(10), TeamSetup::baseline(), 60);

    expect($a->meanDecisionGap)->toBe($b->meanDecisionGap)
        ->and($a->chancesPer90)->toBe($b->chancesPer90)
        ->and($a->progressivePassShare)->toBe($b->progressivePassShare);
});

it('makes a high-vision squad measurably sharper than a low-vision one', function () {
    $evaluator = new SquadEvaluator;

    $low = $evaluator->evaluate(squadOf(6), TeamSetup::baseline(), 150);
    $high = $evaluator->evaluate(squadOf(16), TeamSetup::baseline(), 150);

    expect($high->meanDecisionGap)->toBeLessThan($low->meanDecisionGap)
        ->and($high->progressivePassShare)->toBeGreaterThan($low->progressivePassShare)
        ->and($high->chancesPer90)->toBeGreaterThan($low->chancesPer90);
});

it('lays the default 4-3-3 roster out back to front', function () {
    $players = Roster::build(new Attributes(10, 12, 12, 12, 12, 12));

    expect($players)->toHaveCount(10)
        ->and(array_keys($players))->toBe(range(1, 10));

    expect($players[2]->zone->equals(new Zone(1, 1)))->toBeTrue()
        ->and($players[2]->position)->toBe(Position::Defender)
        ->and($players[8]->zone->equals(new Zone(4, 1)))->toBeTrue()
        ->and($players[8]->position)->toBe(Position::Forward);
});
