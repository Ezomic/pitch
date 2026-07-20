<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Defense;
use App\Sim\Engine\Formation;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;

function attackers(): array
{
    return Roster::build(new Attributes(11, 12, 12, 12, 12, 12));
}

function defenseOf(int $tackling, int $pace): Defense
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes(11, 11, 11, 11, $tackling, $pace);
    }

    return Defense::fromAttributes($bySlot);
}

function squadWith(int $tackling, int $pace): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes(11, 12, 12, 12, $tackling, $pace);
    }

    return new TeamSetup($bySlot, Formation::balanced(), Mentality::Balanced);
}

it('leaves resolution unchanged with no defense', function () {
    $engine = new MatchEngine;
    $players = attackers();

    $default = $engine->simulate($players, 42);
    $none = $engine->simulate($players, 42, Defense::none());

    $a = array_map(fn ($e) => $e->toArray(), $default->events);
    $b = array_map(fn ($e) => $e->toArray(), $none->events);

    expect(json_encode($a))->toBe(json_encode($b));
});

it('concedes fewer chances against a stronger tackling defence', function () {
    $engine = new MatchEngine;
    $players = attackers();

    $weak = 0;
    $strong = 0;
    for ($seed = 1; $seed <= 120; $seed++) {
        $weak += $engine->simulate($players, $seed, defenseOf(4, 8))->shots;
        $strong += $engine->simulate($players, $seed, defenseOf(18, 8))->shots;
    }

    expect($strong)->toBeLessThan($weak);
});

it('concedes fewer chances against a quicker defence', function () {
    $engine = new MatchEngine;
    $players = attackers();

    $slow = 0;
    $quick = 0;
    for ($seed = 1; $seed <= 120; $seed++) {
        $slow += $engine->simulate($players, $seed, defenseOf(11, 4))->shots;
        $quick += $engine->simulate($players, $seed, defenseOf(11, 18))->shots;
    }

    expect($quick)->toBeLessThan($slow);
});

it('makes a high tackling and pace squad concede less than a poor one', function () {
    $evaluator = new SquadEvaluator;

    $solid = $evaluator->evaluate(squadWith(18, 17), TeamSetup::baseline(), 120);
    $porous = $evaluator->evaluate(squadWith(4, 6), TeamSetup::baseline(), 120);

    expect($solid->chancesConcededPer90)->toBeLessThan($porous->chancesConcededPer90)
        ->and($solid->goalsConcededPer90)->toBeLessThanOrEqual($porous->goalsConcededPer90);
});
