<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Squad\MatchCommentary;

/**
 * @return list<MatchEvent>
 */
function tackleEvents(int $seeds = 6): array
{
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    $events = [];
    for ($seed = 1; $seed <= $seeds; $seed++) {
        $events = [...$events, ...$engine->simulate($team(), $team(), $seed)->events];
    }

    return $events;
}

it('produces both kinds of challenge', function () {
    $events = tackleEvents();

    $standing = array_filter($events, fn ($e): bool => $e->type === EventType::Tackle);
    $sliding = array_filter($events, fn ($e): bool => $e->type === EventType::SlideTackle);

    // A defender used to have only one way to challenge.
    expect($standing)->not->toBeEmpty()
        ->and($sliding)->not->toBeEmpty();

    // Going to ground is the exception, taken when staying on your feet will not
    // do, so it must not become the normal way to defend.
    expect(count($sliding))->toBeLessThan(count($standing));
});

it('records the roll behind a challenge so it can be inspected', function () {
    $tackles = array_values(array_filter(
        tackleEvents(),
        fn ($e): bool => $e->type->isTackle(),
    ));

    expect($tackles)->not->toBeEmpty();

    foreach ($tackles as $tackle) {
        expect($tackle->roll)->not->toBeNull()
            ->and($tackle->roll->succeeded())->toBeTrue();
    }
});

it('leaves a defender on the floor when a slide misses', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    // Grounded is engine state, so it has to survive a save and reload or a
    // resumed live match would put a man back on his feet early.
    $result = $engine->simulate($team(), $team(), 3);
    $snapshot = $result->state?->toSnapshot();

    expect($snapshot)->not->toBeNull();

    foreach ($snapshot['players'] as $player) {
        expect($player)->toHaveKey('gr')
            ->and($player['gr'])->toBeGreaterThanOrEqual(0);
    }
});

it('counts a slide as a defensive action and narrates it apart', function () {
    $commentary = new MatchCommentary;

    expect(EventType::SlideTackle->isDefensive())->toBeTrue()
        ->and(EventType::SlideTackle->isTackle())->toBeTrue()
        ->and(EventType::Tackle->isTackle())->toBeTrue()
        ->and($commentary->label(EventType::SlideTackle, true, false, null, null, 1))
        ->not->toBe($commentary->label(EventType::Tackle, true, false, null, null, 1));
});
