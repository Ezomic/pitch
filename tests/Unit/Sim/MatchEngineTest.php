<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

function serializeEvents(array $events): string
{
    return json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $events));
}

function template(int $vision): Attributes
{
    return new Attributes($vision, 12, 12, 12, 12, 12);
}

it('produces a byte-identical event log for the same players and seed', function () {
    $engine = new MatchEngine;
    $players = Roster::build(template(10));

    $first = $engine->simulate($players, 42);
    $second = $engine->simulate($players, 42);

    expect(serializeEvents($first->events))->toBe(serializeEvents($second->events));
});

it('produces a different event log for a different seed', function () {
    $engine = new MatchEngine;
    $players = Roster::build(template(10));

    $a = serializeEvents($engine->simulate($players, 1)->events);
    $b = serializeEvents($engine->simulate($players, 2)->events);

    expect($a)->not->toBe($b);
});

it('generates real match activity', function () {
    $engine = new MatchEngine;
    $result = $engine->simulate(Roster::build(template(12)), 5);

    expect($result->events)->not->toBeEmpty()
        ->and($result->passesCompleted)->toBeGreaterThan(0)
        ->and($result->decisionCount)->toBeGreaterThan(0);
});

it('works the ball through the whole team rather than one long ball', function () {
    $engine = new MatchEngine;
    $result = $engine->simulate(Roster::build(template(60)), 7);

    $carriers = [];
    foreach ($result->events as $event) {
        $carriers[$event->actorId] = true;
    }

    // Distance-aware build-up plus varied possession starts should involve most
    // of the outfielders, not just the deep player and the striker.
    expect(count($carriers))->toBeGreaterThanOrEqual(8);
});

it('starts attacks from different players across a match', function () {
    $engine = new MatchEngine;
    $result = $engine->simulate(Roster::build(template(60)), 7);

    $openers = [];
    $lastMinute = -1;
    foreach ($result->events as $event) {
        if ($event->minute !== $lastMinute) {
            $openers[$event->actorId] = true;
            $lastMinute = $event->minute;
        }
    }

    expect(count($openers))->toBeGreaterThan(1);
});

it('credits the defence with ball-winning events when possession is lost', function () {
    $engine = new MatchEngine;
    $result = $engine->simulate(Roster::build(template(30)), 3);

    $defensive = array_filter($result->events, fn (MatchEvent $e) => $e->type->isDefensive());

    expect($defensive)->not->toBeEmpty();

    foreach ($defensive as $event) {
        expect($event->type)->toBeIn([EventType::Interception, EventType::Tackle, EventType::Clearance])
            ->and($event->actorId)->toBe(MatchEngine::DEFENDER_ID)
            ->and($event->success)->toBeTrue();
    }
});

it('detects a progressive pass only when the ball advances', function () {
    $forward = new MatchEvent(1, EventType::Pass, 1, 2, new Zone(1, 1), new Zone(3, 1), true, null, null);
    $sideways = new MatchEvent(1, EventType::Pass, 1, 2, new Zone(3, 1), new Zone(3, 0), true, null, null);
    $failed = new MatchEvent(1, EventType::Pass, 1, 2, new Zone(1, 1), new Zone(3, 1), false, null, null);

    expect($forward->isProgressivePass())->toBeTrue()
        ->and($sideways->isProgressivePass())->toBeFalse()
        ->and($failed->isProgressivePass())->toBeFalse();
});
