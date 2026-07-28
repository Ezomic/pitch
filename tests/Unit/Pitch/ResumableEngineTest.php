<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\Rng;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PitchResult;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;

function encodeSlice(PitchResult $r): string
{
    return json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $r->events)).'|'.md5((string) json_encode($r->frames));
}

it('resumes in slices byte-identically to one continuous match', function () {
    $engine = new PositionalEngine;

    foreach ([1, 7, 42] as $seed) {
        $oneShot = $engine->simulate(
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            $seed,
        );

        [$state, $rng] = $engine->start(
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            $seed,
        );

        $events = [];
        $frames = [];
        for ($from = 0; $from < $engine->totalTicks(); $from += 137) {
            $slice = $engine->resume($state, $rng, $from, $from + 137);
            $state = $slice->state;
            $events = array_merge($events, $slice->events);
            $frames = array_merge($frames, $slice->frames);
        }

        $sliced = new PitchResult($events, $frames, $state->homeGoals, $state->awayGoals);
        expect(encodeSlice($sliced))->toBe(encodeSlice($oneShot));
    }
});

it('survives a save and load in the middle of a match', function () {
    $engine = new PositionalEngine;

    [$state, $rng] = $engine->start(
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
        7,
    );

    // Play to the midpoint, then snapshot the exact state and Rng.
    $engine->resume($state, $rng, 0, 1350);
    $snapshot = json_decode((string) json_encode($state->toSnapshot()), true);
    $rngState = $rng->stateValue();

    // Continue the live objects to full time.
    $continued = $engine->resume($state, $rng, 1350, $engine->totalTicks());

    // Restore from the snapshot and continue independently.
    $restored = PitchState::fromSnapshot($snapshot);
    $restoredRng = Rng::fromState($rngState);
    $fromSave = $engine->resume($restored, $restoredRng, 1350, $engine->totalTicks());

    expect(encodeSlice($fromSave))->toBe(encodeSlice($continued));
});
