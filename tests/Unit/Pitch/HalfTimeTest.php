<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Rng;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\Geometry;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;

function halfTimeTeam(): array
{
    return Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
}

it('takes the interval at the half-way tick and nowhere else', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(halfTimeTeam(), halfTimeTeam(), 4);

    $firstHalf = $engine->resume($state, $rng, 0, PositionalEngine::HALF_TICK);
    expect($firstHalf->state?->secondHalf)->toBeFalse();

    $result = $engine->resume($firstHalf->state, $rng, PositionalEngine::HALF_TICK, PositionalEngine::HALF_TICK + 1);

    expect($result->state?->secondHalf)->toBeTrue();
});

it('puts everyone back on his own shape and gives the second-half kickoff to the other side', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(halfTimeTeam(), halfTimeTeam(), 4);
    $firstHalf = $engine->resume($state, $rng, 0, PositionalEngine::HALF_TICK);

    $result = $engine->resume($firstHalf->state, $rng, PositionalEngine::HALF_TICK, PositionalEngine::HALF_TICK + 1);
    $after = $result->state;

    expect($after?->possessing)->toBe(1)
        ->and($result->frames[0]['j'])->toBeTrue();

    $moved = 0;
    foreach ($after->players as $player) {
        if (! $player->isGoalkeeper() && $player->pos->distanceTo($player->anchor) > 0.12) {
            $moved++;
        }
    }

    // Only the kickoff pair and a defender pushed out of the centre circle are
    // anywhere but home, and one tick of movement cannot take them far.
    expect($moved)->toBeLessThanOrEqual(1);
});

it('plays the same match whether or not it is paused at the interval', function () {
    $engine = new PositionalEngine;
    $continuous = $engine->simulate(halfTimeTeam(), halfTimeTeam(), 9);

    [$state, $rng] = $engine->start(halfTimeTeam(), halfTimeTeam(), 9);
    $first = $engine->resume($state, $rng, 0, PositionalEngine::HALF_TICK);

    $reloaded = PitchState::fromSnapshot(json_decode((string) json_encode($first->state?->toSnapshot()), true));
    $rng = Rng::fromState($rng->stateValue());
    $second = $engine->resume($reloaded, $rng, PositionalEngine::HALF_TICK, $engine->totalTicks());

    expect($first->homeGoals + $second->homeGoals)->toBeGreaterThanOrEqual(0)
        ->and($second->homeGoals)->toBe($continuous->homeGoals)
        ->and($second->awayGoals)->toBe($continuous->awayGoals)
        ->and([...$first->frames, ...$second->frames])->toEqual($continuous->frames);
});

it('keeps the second half through a goal kickoff', function () {
    $engine = new PositionalEngine;

    foreach (range(1, 6) as $seed) {
        $result = $engine->simulate(halfTimeTeam(), halfTimeTeam(), $seed);

        expect($result->state?->secondHalf)->toBeTrue();
    }
});

it('swaps the ends in the second half and not before', function () {
    $engine = new PositionalEngine;
    $live = new LivePitch;
    [$state, $rng] = $engine->start(halfTimeTeam(), halfTimeTeam(), 4);

    $first = $engine->resume($state, $rng, 0, 8);
    $toHalf = $engine->resume($first->state, $rng, 8, PositionalEngine::HALF_TICK);
    $second = $engine->resume($toHalf->state, $rng, PositionalEngine::HALF_TICK, PositionalEngine::HALF_TICK + 4);

    $before = $live->frames($first->frames, 0)[0];
    $after = $live->frames($second->frames, PositionalEngine::HALF_TICK)[0];

    expect($before['p'])->toBe($first->frames[0]['p'])
        ->and($after['p'])->toBe(Geometry::swapEnds($second->frames[2])['p'])
        ->and($after['p'][1][0])->toBe(1.0 - $second->frames[2]['p'][1][0]);
});
