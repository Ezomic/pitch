<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Vec2;

function insideCentreCircle(Vec2 $pos): bool
{
    $dx = $pos->x - 0.5;
    $dy = $pos->y - 0.5;

    return ($dx / PositionalEngine::CIRCLE_RX) ** 2 + ($dy / PositionalEngine::CIRCLE_RY) ** 2 < 1.0;
}

it('leaves no defending player inside the centre circle at kickoff', function () {
    $engine = new PositionalEngine;

    [$state] = $engine->start(
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        7,
    );

    // Home (side 0) kicks off, so side 1 is defending the restart.
    $defenders = array_filter(
        $state->players,
        fn ($p) => $p->side === 1 && ! $p->isGoalkeeper(),
    );

    expect($defenders)->not->toBeEmpty();

    foreach ($defenders as $defender) {
        expect(insideCentreCircle($defender->pos))->toBeFalse();
    }
});

it('puts the kicking-off side on the ball at the centre spot', function () {
    $engine = new PositionalEngine;

    [$state] = $engine->start(
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        7,
    );

    $carrier = collect($state->players)->firstWhere('id', $state->carrierId);

    expect($carrier)->not->toBeNull()
        ->and($carrier->side)->toBe(0)
        ->and($carrier->pos->x)->toBe(0.5)
        ->and($carrier->pos->y)->toBe(0.5);
});
