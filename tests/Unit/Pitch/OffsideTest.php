<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Rng;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\Offside;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Restarts;
use App\Sim\Pitch\Vec2;
use App\Sim\Squad\MatchCommentary;

const PASSER = 5;
const RECEIVER = 9;
const OTHER_ATTACKER = 8;

/**
 * A hand-built moment: home (attacking toward x = 1) has the ball with slot 5,
 * away sit in a flat line at x = 0.70 with the keeper on his own goal line. The
 * attackers are all behind the ball except the two put forward by each test.
 */
function offsideScene(float $receiverX = 0.80, int $passing = 99): PitchState
{
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, $passing, 72, 72, 72, 72));

    [$state] = $engine->start($team(), $team(), 1);

    foreach ($state->players as $player) {
        $slot = $player->slot;
        $player->pos = match (true) {
            $player->side === 1 && $slot === 0 => new Vec2(0.97, 0.5),
            $player->side === 1 => new Vec2(0.70, 0.05 + $slot * 0.09),
            $slot === 0 => new Vec2(0.03, 0.5),
            default => new Vec2(0.40, 0.05 + $slot * 0.09),
        };
    }

    $state->players[PASSER]->pos = new Vec2(0.55, 0.5);
    $state->players[RECEIVER]->pos = new Vec2($receiverX, 0.4);
    $state->carrierId = PASSER;
    $state->possessing = 0;
    $state->ball = $state->players[PASSER]->pos;
    $state->restartTaker = PitchState::NO_CARRIER;

    return $state;
}

function flagged(PitchState $state, int $passer = PASSER, int $receiver = RECEIVER): bool
{
    return Offside::flags($state, $state->players[$passer], $state->players[$receiver]);
}

/**
 * Play a pass through the engine's own pass(), exactly as the carrier would.
 *
 * @return list<MatchEvent>
 */
function playPass(PitchState $state, int $seed, int $minute = 31): array
{
    $events = [];
    $rng = new Rng($seed);
    $method = new ReflectionMethod(PositionalEngine::class, 'pass');
    $method->invokeArgs(new PositionalEngine, [$state, &$events, $rng, $minute, $state->players[PASSER], $state->players[RECEIVER]]);

    return $events;
}

/**
 * The first seed whose scene ends in an offside call.
 *
 * @return array{list<MatchEvent>, PitchState}
 */
function calledOffside(float $receiverX = 0.80): array
{
    for ($seed = 1; $seed <= 40; $seed++) {
        $state = offsideScene($receiverX);
        $events = playPass($state, $seed);

        if (collect($events)->contains(fn (MatchEvent $e): bool => $e->type === EventType::Offside)) {
            return [$events, $state];
        }
    }

    throw new RuntimeException('No seed produced an offside call.');
}

it('flags a receiver nearer the goal line than the ball and the second-last opponent', function () {
    expect(flagged(offsideScene(0.80)))->toBeTrue();
});

it('lets a receiver level with the second-last opponent play on', function () {
    expect(flagged(offsideScene(0.70)))->toBeFalse()
        ->and(flagged(offsideScene(0.69)))->toBeFalse();
});

it('counts the goalkeeper as one of the two last defenders', function () {
    $state = offsideScene(0.80);
    $state->players[101]->pos = new Vec2(0.90, 0.5);

    // Keeper and one defender are both behind him, so he is not past two opponents.
    expect(flagged($state))->toBeFalse();

    $state->players[RECEIVER]->pos = new Vec2(0.93, 0.4);
    expect(flagged($state))->toBeTrue();
});

it('has no offside line to speak of when the keeper has come out', function () {
    $state = offsideScene(0.80);
    $state->players[100]->pos = new Vec2(0.60, 0.5);
    $state->players[101]->pos = new Vec2(0.90, 0.5);

    // Last defender at 0.90, second-last at 0.70: the keeper no longer helps hold the line.
    expect(Offside::line($state, 0))->toEqualWithDelta(0.70, 1e-9)
        ->and(flagged($state))->toBeTrue();
});

it('never calls offside in a side\'s own half', function () {
    $state = offsideScene(0.45);

    foreach ($state->players as $player) {
        if ($player->side === 1) {
            $player->pos = new Vec2(0.20, 0.5);
        }
    }

    expect(flagged($state))->toBeFalse();
});

it('does not flag a receiver who is behind the ball', function () {
    $state = offsideScene(0.80);
    $state->players[PASSER]->pos = new Vec2(0.85, 0.5);

    expect(flagged($state))->toBeFalse();
});

it('judges only the player the pass is played to', function () {
    $state = offsideScene(0.80);
    $state->players[OTHER_ATTACKER]->pos = new Vec2(0.85, 0.7);
    $state->players[7]->pos = new Vec2(0.62, 0.3);

    expect(flagged($state, PASSER, OTHER_ATTACKER))->toBeTrue()
        ->and(flagged($state, PASSER, RECEIVER))->toBeTrue()
        ->and(flagged($state, PASSER, 7))->toBeFalse();
});

it('ignores an opponent who has been sent off', function () {
    $state = offsideScene(0.80);
    $state->players[101]->pos = new Vec2(0.95, 0.5);

    expect(flagged($state))->toBeFalse();

    $state->players[101]->sentOff = true;
    expect(flagged($state))->toBeTrue();
});

it('reads the line the same way for the side attacking x = 0', function () {
    $state = offsideScene();

    foreach ($state->players as $player) {
        $player->pos = new Vec2(1.0 - $player->pos->x, $player->pos->y);
    }

    foreach ($state->players as $player) {
        if ($player->side === 0 && ! $player->isGoalkeeper()) {
            $player->pos = new Vec2(0.30, 0.05 + $player->slot * 0.09);
        }
    }

    $state->players[105]->pos = new Vec2(0.45, 0.5);
    $state->players[109]->pos = new Vec2(0.20, 0.4);

    // Away now play the ball toward x = 0 with home holding the line at x = 0.30.
    expect(Offside::flags($state, $state->players[105], $state->players[109]))->toBeTrue();

    $state->players[109]->pos = new Vec2(0.31, 0.4);
    expect(Offside::flags($state, $state->players[105], $state->players[109]))->toBeFalse();
});

it('does not call offside for a ball received straight from a restart', function () {
    $state = offsideScene(0.80);
    expect(flagged($state))->toBeTrue();

    $state->restartTaker = PASSER;
    expect(flagged($state))->toBeFalse();
});

it('marks a throw-in, corner and goal kick as restarts that cannot be offside, and a free kick as one that can', function () {
    $restarts = new Restarts;

    foreach ([EventType::ThrowIn, EventType::Corner, EventType::GoalKick] as $type) {
        $state = offsideScene();
        $events = [];
        $restarts->awardRestart($state, $events, 10, $type, 0, new Vec2(0.5, 0.02));

        expect($state->restartTaker)->toBe($state->carrierId, $type->value);
    }

    $state = offsideScene();
    $events = [];
    $restarts->awardRestart($state, $events, 10, EventType::Foul, 0, new Vec2(0.5, 0.5));

    expect($state->restartTaker)->toBe(PitchState::NO_CARRIER);
});

it('gives the indirect free kick to the defending side where the receiver stood', function () {
    [$events, $state] = calledOffside(0.80);

    $pass = $events[0];
    $call = end($events);

    expect($pass->type)->toBe(EventType::Pass)
        ->and($pass->success)->toBeFalse('an offside pass is not a completed pass')
        ->and($pass->targetId)->toBe(RECEIVER)
        ->and($call->type)->toBe(EventType::Offside)
        ->and($call->minute)->toBe(31)
        ->and($call->actorId)->toBe(RECEIVER)
        ->and($call->targetId)->toBe(PASSER);

    // The free kick: defenders have it, on the spot where the receiver was when the ball was played.
    $taker = $state->carrier();

    expect($taker)->not->toBeNull()
        ->and($taker->side)->toBe(1)
        ->and($taker->isGoalkeeper())->toBeFalse()
        ->and($state->possessing)->toBe(1)
        ->and($state->ball->toArray())->toBe([0.80, 0.4])
        ->and($taker->pos->toArray())->toBe([0.80, 0.4])
        ->and($state->deadBall)->toBeGreaterThan(0)
        ->and($state->ballTarget)->toBeNull()
        ->and($state->restartTaker)->toBe(PitchState::NO_CARRIER);
});

it('lets the pass go through when the receiver is onside', function () {
    $state = offsideScene(0.66);
    $events = playPass($state, 3);

    expect(collect($events)->contains(fn (MatchEvent $e): bool => $e->type === EventType::Offside))->toBeFalse();
});

it('does not call offside on a pass that was cut out', function () {
    $cutOut = 0;

    for ($seed = 1; $seed <= 60; $seed++) {
        $state = offsideScene(0.80, passing: 1);
        $events = playPass($state, $seed);
        $types = array_map(fn (MatchEvent $e): EventType => $e->type, $events);

        if ($events[0]->roll->succeeded()) {
            continue;
        }

        $cutOut++;
        expect($types)->not->toContain(EventType::Offside);
    }

    // The defender's touch is a deliberate play, so the receiver is never involved.
    expect($cutOut)->toBeGreaterThan(5);
});

it('does not call a receiver offside for running past the line after the ball is played', function () {
    $engine = new PositionalEngine;
    $found = false;

    for ($seed = 1; $seed <= 40 && ! $found; $seed++) {
        $state = offsideScene(0.66);
        $events = playPass($state, $seed);

        if ($state->ballTo !== RECEIVER) {
            continue;
        }

        $found = true;
        $state->players[RECEIVER]->pos = new Vec2(0.88, 0.4);
        $state->ballTarget = $state->players[RECEIVER]->pos;

        $rest = $engine->resume($state, new Rng($seed), 600, 640);

        expect(array_merge($events, $rest->events))->each(fn ($e) => $e->type->not->toBe(EventType::Offside));
    }

    expect($found)->toBeTrue();
});

it('also flags a cross played to a receiver beyond the line', function () {
    $called = false;

    for ($seed = 1; $seed <= 60 && ! $called; $seed++) {
        $state = offsideScene(0.88);
        $events = [];
        $method = new ReflectionMethod(PositionalEngine::class, 'cross');
        $method->invokeArgs(new PositionalEngine, [$state, &$events, new Rng($seed), 40, $state->players[PASSER], $state->players[RECEIVER]]);

        $called = collect($events)->contains(fn (MatchEvent $e): bool => $e->type === EventType::Offside);

        if ($called) {
            expect($events[0]->type)->toBe(EventType::Cross)
                ->and($events[0]->success)->toBeFalse()
                ->and($state->crossPending)->toBeFalse()
                ->and($state->possessing)->toBe(1);
        }
    }

    expect($called)->toBeTrue();
});

it('produces offsides in a match, a handful rather than a flood', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    $total = 0;
    for ($seed = 1; $seed <= 10; $seed++) {
        $total += count(array_filter(
            $engine->simulate($team(), $team(), $seed)->events,
            fn (MatchEvent $e): bool => $e->type === EventType::Offside,
        ));
    }

    expect($total / 10)->toBeGreaterThan(0.5)->toBeLessThan(10.0);
});

it('never calls offside against the side that has just been awarded the restart', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    for ($seed = 1; $seed <= 6; $seed++) {
        $events = $engine->simulate($team(), $team(), $seed)->events;

        foreach ($events as $i => $event) {
            if ($event->type !== EventType::Offside) {
                continue;
            }

            $pass = $events[$i - 1];

            expect($pass->type->value)->toBeIn(['pass', 'cross'])
                ->and($pass->success)->toBeFalse()
                ->and($pass->actorId)->toBe($event->targetId)
                ->and($pass->targetId)->toBe($event->actorId)
                ->and(intdiv($pass->actorId, 100))->toBe(intdiv($event->actorId, 100));
        }
    }
});

it('plays the same match twice, offsides included', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    foreach ([2, 9] as $seed) {
        $a = $engine->simulate($team(), $team(), $seed);
        $b = $engine->simulate($team(), $team(), $seed);

        expect(json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $a->events)))
            ->toBe(json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $b->events)))
            ->and(md5((string) json_encode($a->frames)))->toBe(md5((string) json_encode($b->frames)));
    }
});

it('keeps the restart exemption across a save and reload', function () {
    $state = offsideScene();
    $state->restartTaker = PASSER;

    $restored = PitchState::fromSnapshot(json_decode((string) json_encode($state->toSnapshot()), true));

    expect($restored->restartTaker)->toBe(PASSER);
});

it('loads a match saved before offside existed, with no restart exemption', function () {
    $snapshot = json_decode((string) json_encode(offsideScene()->toSnapshot()), true);
    unset($snapshot['restartTaker']);

    expect(PitchState::fromSnapshot($snapshot)->restartTaker)->toBe(PitchState::NO_CARRIER);
});

it('narrates an offside with the minute, the receiver and the passer', function () {
    $commentary = new MatchCommentary;
    $event = new MatchEvent(63, EventType::Offside, 9, 5, new Zone(8, 4), null, true, null, null);

    $moment = $commentary->moment($event, [9 => 'Alice', 5 => 'Bob']);

    expect($moment)->not->toBeNull()
        ->and($moment->minute)->toBe(63)
        ->and($moment->kind)->toBe('setpiece')
        ->and($moment->text)->toContain('Alice')
        ->and($moment->text)->toContain('Bob')
        ->and($commentary->label(EventType::Offside, true, false, 'Alice', 'Bob', 1))->toBe('Offside');
});

it('narrates an unnamed side without repeating itself', function () {
    $commentary = new MatchCommentary;
    $event = new MatchEvent(63, EventType::Offside, 109, 105, new Zone(8, 4), null, true, null, null);

    $moment = $commentary->moment($event, [109 => 'Opposition', 105 => 'Opposition']);

    expect($moment?->text)->not->toContain('Opposition Opposition')
        ->and($moment?->text)->not->toMatch('/Opposition.*Opposition/');
});
