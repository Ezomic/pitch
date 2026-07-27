<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PitchResult;
use App\Sim\Pitch\PositionalEngine;

function pitchMatch(int $seed): PitchResult
{
    $home = Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
    $away = Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    return (new PositionalEngine)->simulate($home, $away, $seed);
}

function encodePitch(PitchResult $result): string
{
    $events = json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $result->events));

    return $events.'|'.md5((string) json_encode($result->frames));
}

it('produces a byte-identical match for the same players and seed', function () {
    foreach ([1, 7, 42] as $seed) {
        expect(encodePitch(pitchMatch($seed)))->toBe(encodePitch(pitchMatch($seed)));
    }
});

it('produces a different match for a different seed', function () {
    expect(encodePitch(pitchMatch(1)))->not->toBe(encodePitch(pitchMatch(2)));
});

it('emits a full position stream and real activity', function () {
    $result = pitchMatch(7);

    $passes = count(array_filter($result->events, fn (MatchEvent $e) => $e->type === EventType::Pass));

    expect($result->frames)->not->toBeEmpty()
        ->and(count($result->frames))->toBeGreaterThan(2000)
        ->and($result->frames[0]['p'])->toHaveCount(22)
        ->and($passes)->toBeGreaterThan(100);
});

it('builds up: passing dominates the action', function () {
    foreach ([7, 21, 11, 42, 5] as $seed) {
        $counts = [];
        foreach (pitchMatch($seed)->events as $event) {
            $counts[$event->type->value] = ($counts[$event->type->value] ?? 0) + 1;
        }

        $shots = ($counts['shot'] ?? 0) + ($counts['header'] ?? 0);
        expect($counts['pass'] ?? 0)->toBeGreaterThan($shots * 3);
    }
});

it('involves both whole teams', function () {
    $actors = [];
    foreach (pitchMatch(7)->events as $event) {
        $actors[$event->actorId] = true;
    }

    // Actor ids are side*100 + slot, so a spread across both sides proves the
    // ball moves through the team rather than one or two players.
    expect(count($actors))->toBeGreaterThanOrEqual(10);
});

it('is a two-way match where possession changes hands', function () {
    $sides = [];
    foreach (pitchMatch(7)->frames as $frame) {
        $sides[$frame['s']] = true;
    }

    expect($sides)->toHaveKey(0)->toHaveKey(1);
});

it('keeps the ball continuous in open play', function () {
    $result = pitchMatch(7);
    $frames = $result->frames;

    // Every step is a small glide (a carry ~0.01, a pass or shot in flight
    // ~0.07-0.11). The only legitimate jumps are dead-ball repositions: a kick-off
    // after a goal, and set pieces where the ball is placed at a throw, corner or
    // goal-kick spot. So big jumps should number no more than those restarts. No
    // teleporting mid-play, unlike the derived layer.
    $restarts = $result->homeGoals + $result->awayGoals + 1; // goals + opening kick-off
    foreach ($result->events as $event) {
        if (in_array($event->type, [EventType::ThrowIn, EventType::Corner, EventType::GoalKick], true)) {
            $restarts++;
        }
    }

    $big = 0;
    for ($i = 1; $i < count($frames); $i++) {
        $a = $frames[$i - 1]['b'];
        $b = $frames[$i]['b'];
        if (sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2) > 0.15) {
            $big++;
        }
    }

    expect($big)->toBeLessThanOrEqual($restarts);
});

it('produces set pieces: fouls, throw-ins, corners and goal kicks', function () {
    $counts = [];
    foreach (pitchMatch(7)->events as $event) {
        $counts[$event->type->value] = ($counts[$event->type->value] ?? 0) + 1;
    }

    expect($counts[EventType::Foul->value] ?? 0)->toBeGreaterThan(0)
        ->and($counts[EventType::ThrowIn->value] ?? 0)->toBeGreaterThan(0)
        ->and($counts[EventType::Corner->value] ?? 0)->toBeGreaterThan(0)
        ->and($counts[EventType::GoalKick->value] ?? 0)->toBeGreaterThan(0);
});

it('awards the occasional penalty for a foul in the box', function () {
    // Penalties are rare (~0.25 a match), so sample a spread of seeds.
    $penalties = 0;
    foreach (range(1, 20) as $seed) {
        foreach (pitchMatch($seed)->events as $event) {
            if ($event->type === EventType::Penalty) {
                $penalties++;
            }
        }
    }

    expect($penalties)->toBeGreaterThan(0);
});

it('defends with a block goal-side of the ball while forwards stay high', function () {
    $frames = pitchMatch(7)->frames;

    // While the home side attacks (possessing 0), the away defensive block, its
    // back four and midfield (stream indices 12..18; 11 is the keeper, 19..21 are
    // the forwards), holds between the ball and the goal it defends at x=1: their
    // average x is ahead of the ball. This is a real defensive shape, not players
    // chasing the ball.
    $goalSide = 0;
    $forwardsHigh = 0;
    $total = 0;
    foreach ($frames as $frame) {
        if ($frame['s'] !== 0) {
            continue;
        }

        $block = 0.0;
        for ($i = 12; $i < 19; $i++) {
            $block += $frame['p'][$i][0];
        }

        // The away forwards (19..21) deliberately stay high as a counter outlet,
        // upfield of the block rather than tracking back into it.
        $forwards = 0.0;
        for ($i = 19; $i < 22; $i++) {
            $forwards += $frame['p'][$i][0];
        }

        if ($block / 7 > $frame['b'][0]) {
            $goalSide++;
        }
        if ($forwards / 3 < $block / 7) {
            $forwardsHigh++;
        }
        $total++;
    }

    expect($goalSide / $total)->toBeGreaterThan(0.75)
        ->and($forwardsHigh / $total)->toBeGreaterThan(0.9);
});

it('produces realistic scorelines across seeds', function () {
    $seeds = [7, 21, 11, 42, 5, 1, 2, 3, 8, 13];
    $goals = 0;
    foreach ($seeds as $seed) {
        $result = pitchMatch($seed);
        $goals += $result->homeGoals + $result->awayGoals;
    }

    // Neither a shutout every week nor a cricket score: through-balls create
    // chances but the block and keeper keep goals in a sane band.
    $average = $goals / count($seeds);
    expect($average)->toBeGreaterThan(0.5)->toBeLessThan(6.0);
});
