<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;

function team(int $rating): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes($rating, $rating, $rating, $rating, $rating, $rating);
    }

    return new TeamSetup($bySlot, Formation::balanced(), Mentality::Balanced);
}

it('resolves a fixture deterministically for a fixed seed', function () {
    $resolver = new FixtureResolver;

    $a = $resolver->resolve(team(14), team(9), 5);
    $b = $resolver->resolve(team(14), team(9), 5);

    expect($a)->toBe($b)
        ->and($a)->toHaveKeys(['home', 'away']);
});

it('gives the stronger side more goals over many fixtures', function () {
    $resolver = new FixtureResolver;

    $strong = 0;
    $weak = 0;
    for ($seed = 1; $seed <= 80; $seed++) {
        $result = $resolver->resolve(team(72), team(38), $seed);
        $strong += $result['home'];
        $weak += $result['away'];
    }

    expect($strong)->toBeGreaterThan($weak);
});
