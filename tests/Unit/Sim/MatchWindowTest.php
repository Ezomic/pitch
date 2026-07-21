<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

function windowPlayers(): array
{
    return Roster::build(new Attributes(12, 12, 12, 12, 12, 12));
}

it('reproduces the full match when given the default window', function () {
    $engine = new MatchEngine;
    $players = windowPlayers();

    $default = $engine->simulate($players, 7);
    $explicit = $engine->simulate($players, 7, null, null, 1.0, 0, 90);

    $serialize = fn ($result) => array_map(fn ($e) => $e->toArray(), $result->events);

    expect($serialize($default))->toBe($serialize($explicit));
});

it('only emits minutes inside the requested window', function () {
    $engine = new MatchEngine;

    $secondHalf = $engine->simulate(windowPlayers(), 7, null, null, 1.0, 45, 90);

    expect($secondHalf->events)->not->toBeEmpty();
    foreach ($secondHalf->events as $event) {
        expect($event->minute)->toBeGreaterThanOrEqual(45)->toBeLessThan(90);
    }
});
