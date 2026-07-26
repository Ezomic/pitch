<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\MatchResult;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchTimeline;

function ev(int $minute, EventType $type, int $fromX, int $fromY, bool $success, ?int $targetId = null, ?Zone $to = null): MatchEvent
{
    return new MatchEvent($minute, $type, 1, $targetId, new Zone($fromX, $fromY), $to, $success, null, null);
}

it('places the home side left to right and mirrors the opponent', function () {
    $home = new MatchResult([ev(10, EventType::Shot, 5, 2, true)]);
    $away = new MatchResult([ev(10, EventType::Pass, 5, 0, false, targetId: 3)]);

    $frames = (new MatchTimeline)->build($home, $away, [1 => 'Striker']);

    $homeFrame = collect($frames)->firstWhere('s', 0);
    $awayFrame = collect($frames)->firstWhere('s', 1);

    // Home shot from the attacking edge sits at the far right; the mirrored
    // opponent event from their attacking edge sits at the far left.
    expect($homeFrame['x1'])->toBe(1.0)
        ->and($awayFrame['x1'])->toBe(0.0)
        ->and($homeFrame['goal'])->toBeTrue()
        ->and($homeFrame['actor'])->toBe('Striker')
        ->and($awayFrame['actor'])->toBe('Opposition');
});

it('carries both endpoints and the receiver for a pass', function () {
    $home = new MatchResult([
        ev(12, EventType::Pass, 2, 1, true, targetId: 7, to: new Zone(4, 1)),
    ]);

    $frames = (new MatchTimeline)->build($home, null, [1 => 'Smith', 7 => 'Jones']);
    $frame = $frames[0];

    expect($frame['actor'])->toBe('Smith')
        ->and($frame['target'])->toBe('Jones')
        ->and($frame['x1'])->toBe(round(2 / Zone::MAX_X, 3))
        ->and($frame['x2'])->toBe(round(4 / Zone::MAX_X, 3));
});

it('sends a shot without a target zone toward the goal', function () {
    $home = new MatchResult([ev(20, EventType::Shot, 4, 2, false)]);

    $frame = (new MatchTimeline)->build($home, null, [1 => 'Striker'])[0];

    // No `to` on the event, so the ball is aimed at the attacking goal mouth.
    expect($frame['x2'])->toBe(1.0)
        ->and($frame['y2'])->toBe(0.5)
        ->and($frame['target'])->toBeNull();
});

it('marks a possession start after a shot or a lost duel', function () {
    $home = new MatchResult([
        ev(5, EventType::Pass, 0, 2, true, targetId: 2),   // opens possession
        ev(5, EventType::Pass, 2, 2, true, targetId: 3),   // continues
        ev(5, EventType::Shot, 5, 2, false),               // ends possession
        ev(6, EventType::Dribble, 0, 2, true),             // new possession
    ]);

    $frames = (new MatchTimeline)->build($home, null, []);
    $starts = array_map(fn ($f) => $f['start'], $frames);

    expect($starts)->toBe([true, false, false, true]);
});

it('is deterministic and its goal frames match the scoreline', function () {
    $engine = new MatchEngine;
    $players = Roster::build(new Attributes(70, 70, 70, 70, 70, 70));
    $home = $engine->simulate($players, 7);
    $away = $engine->simulate($players, 11);

    $a = (new MatchTimeline)->build($home, $away, []);
    $b = (new MatchTimeline)->build($home, $away, []);

    expect($a)->toBe($b); // deterministic

    $minutes = array_map(fn ($f) => $f['m'], $a);
    $sorted = $minutes;
    sort($sorted);
    expect($minutes)->toBe($sorted); // ordered by minute

    $homeGoals = count(array_filter($a, fn ($f) => $f['s'] === 0 && $f['goal']));
    $awayGoals = count(array_filter($a, fn ($f) => $f['s'] === 1 && $f['goal']));
    expect($homeGoals)->toBe($home->goals)
        ->and($awayGoals)->toBe($away->goals);
});
