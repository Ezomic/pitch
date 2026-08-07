<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PositionalEngine;

/**
 * A live match is simulated a slice at a time and the client picks the slice
 * size, so the commentary must not depend on where those boundaries fall.
 *
 * It used to. The phrasing key folded in each event's position within the batch
 * it was generated in, and the feed samples on that key to decide what is worth
 * a line at all, so the same match narrated differently depending on how it had
 * been chunked. PITCH-150 worked around it by replaying the stored feed rather
 * than regenerating it; this is the fix.
 */
function commentedMatch(int $seed): array
{
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    return (new PositionalEngine)->simulate($team(), $team(), $seed)->events;
}

/**
 * @return array<int, string>
 */
function commentaryNames(): array
{
    $names = [];
    foreach ([0, 1] as $side) {
        foreach (range(0, 10) as $slot) {
            $names[$side * 100 + $slot] = $side === 0 ? "Home {$slot}" : 'Opposition';
        }
    }

    return $names;
}

it('narrates a match the same way however it was sliced', function () {
    $events = commentedMatch(5);
    $live = new LivePitch;
    $names = commentaryNames();

    $whole = $live->moments($events, $names);

    foreach ([7, 37, 120] as $size) {
        $chunked = [];
        foreach (array_chunk($events, $size) as $chunk) {
            $chunked = [...$chunked, ...$live->moments($chunk, $names)];
        }

        expect($chunked)->toEqual($whole, "Chunk size {$size} produced a different feed.");
    }
});

it('still narrates the same match the same way on a second run', function () {
    $events = commentedMatch(11);
    $live = new LivePitch;
    $names = commentaryNames();

    expect($live->moments($events, $names))->toEqual($live->moments($events, $names));
});

it('keeps the feed varied rather than collapsing to one phrasing', function () {
    $moments = (new LivePitch)->moments(commentedMatch(3), commentaryNames());

    expect($moments)->not->toBeEmpty();

    // Deriving the key from the event alone could have made every line identical
    // for a given kind of event; it must still read like commentary.
    $saves = array_values(array_filter($moments, fn (array $m): bool => $m['kind'] === 'save'));

    if (count($saves) >= 4) {
        expect(count(array_unique(array_column($saves, 'text'))))->toBeGreaterThan(1);
    }

    expect(count(array_unique(array_column($moments, 'text'))))->toBeGreaterThan(5);
});
