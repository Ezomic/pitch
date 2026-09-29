<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Vec2;
use App\Sim\Squad\TeamSetup;

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

it('keeps both sides\' mentality through the kickoff after a goal', function () {
    $engine = new PositionalEngine;

    [$state, $rng] = $engine->start(TeamSetup::baseline()->attackers(), TeamSetup::baseline()->attackers(), 42);
    $state->homeMentality = 'defensive';
    $state->awayMentality = 'attacking';

    $state = $engine->resume($state, $rng, 0, 5)->state;

    expect($state->homeMentality)->toBe('defensive')
        ->and($state->awayMentality)->toBe('attacking');

    // A goal on the previous tick owes a kickoff, which the next tick takes on a
    // fresh state. A goal restarts play, not the match.
    $state->homeGoals = 1;
    $state->pendingKickoff = 1;

    $kickoff = $engine->resume($state, $rng, 5, 6)->state;

    expect($kickoff)->not->toBe($state)
        ->and($kickoff->pendingKickoff)->toBeNull()
        ->and($kickoff->homeGoals)->toBe(1)
        ->and($kickoff->homeMentality)->toBe('defensive')
        ->and($kickoff->awayMentality)->toBe('attacking');
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
