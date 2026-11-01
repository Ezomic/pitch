<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Rng;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\Injuries;
use App\Sim\Pitch\PitchResult;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PlayerState;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Vec2;
use App\Sim\Squad\MatchCommentary;

function injuryTeam(): array
{
    return Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
}

function injuryRun(PitchState $state, Rng $rng, int $from, int $to): PitchResult
{
    return (new PositionalEngine)->resume($state, $rng, $from, $to);
}

/**
 * @return list<MatchEvent>
 */
function onlyInjuries(array $events, EventType $type = EventType::Injury): array
{
    return array_values(array_filter($events, fn (MatchEvent $e): bool => $e->type === $type));
}

/** Make a player certain to be hurt on the next tick. */
function makeCertain(PitchState $state, int $side, int $slot): void
{
    $player = $state->players[PlayerState::id($side, $slot)];
    $player->proneness = 0.5;
    $player->strain = 1.0;
}

function freshPlayer(float $stamina = 1.0): PlayerState
{
    $player = new PlayerState(3, 0, 3, Position::Midfielder, new Vec2(0.5, 0.5), new Attributes(70, 70, 70, 70, 70, 70));
    $player->stamina = $stamina;

    return $player;
}

it('hurts players in a match, deterministically from the seed', function () {
    $engine = new PositionalEngine;
    $log = function (int $seed) use ($engine): array {
        $events = onlyInjuries($engine->simulate(injuryTeam(), injuryTeam(), $seed)->events);

        return array_map(fn (MatchEvent $e): array => [$e->minute, $e->actorId], $events);
    };

    $total = 0;
    foreach (range(1, 12) as $seed) {
        $first = $log($seed);
        $total += count($first);

        expect($log($seed))->toBe($first);
    }

    expect($total)->toBeGreaterThan(0);
});

it('plays a whole match identically twice, injuries included', function () {
    $engine = new PositionalEngine;
    $hurtSeed = null;

    foreach (range(1, 40) as $seed) {
        if (onlyInjuries($engine->simulate(injuryTeam(), injuryTeam(), $seed)->events) !== []) {
            $hurtSeed = $seed;
            break;
        }
    }

    $a = $engine->simulate(injuryTeam(), injuryTeam(), $hurtSeed);
    $b = $engine->simulate(injuryTeam(), injuryTeam(), $hurtSeed);

    expect($hurtSeed)->not->toBeNull()
        ->and($a->frames)->toEqual($b->frames)
        ->and($a->events)->toEqual($b->events)
        ->and($a->state?->toSnapshot())->toEqual($b->state?->toSnapshot());
});

it('leaves a match in which nobody is hurt exactly as it was before injuries existed', function () {
    $engine = new PositionalEngine;
    $checked = 0;

    foreach (range(1, 12) as $seed) {
        $with = $engine->simulate(injuryTeam(), injuryTeam(), $seed);

        if (onlyInjuries($with->events) !== []) {
            continue;
        }

        [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), $seed);
        foreach ($state->players as $player) {
            $player->proneness = null;
        }
        $without = $engine->resume($state, $rng, 0, $engine->totalTicks());
        $checked++;

        expect($with->frames)->toEqual($without->frames)
            ->and($with->events)->toEqual($without->events);
    }

    expect($checked)->toBeGreaterThan(3);
});

it('takes no draw from the match stream', function () {
    [, $rng] = (new PositionalEngine)->start(injuryTeam(), injuryTeam(), 9);

    expect($rng->stateValue())->toBe((new Rng(9))->stateValue());
});

it('costs a fresh player nothing for running, and a tired one more for a sprint', function () {
    $injuries = new Injuries;
    $fresh = freshPlayer(1.0);
    $half = freshPlayer(0.5);
    $tired = freshPlayer(0.2);
    $spent = freshPlayer(0.0);
    $sprinting = freshPlayer(0.0);

    foreach ([$fresh, $half, $tired, $spent] as $player) {
        $injuries->run($player, sprint: false);
    }
    $injuries->run($sprinting, sprint: true);

    expect($fresh->strain)->toBe(0.0)
        ->and($half->strain)->toBe(0.0)
        ->and($tired->strain)->toBeGreaterThan(0.0)
        ->and($spent->strain)->toBeGreaterThan($tired->strain)
        ->and($sprinting->strain)->toBeGreaterThan($spent->strain);
});

it('hurts more of a tired side than a fresh one over the same matches', function () {
    $engine = new PositionalEngine;
    $count = function (float $stamina) use ($engine): int {
        $hurt = 0;
        foreach (range(1, 12) as $seed) {
            [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), $seed);
            foreach ($state->players as $player) {
                $player->stamina = $stamina;
            }
            $hurt += count(onlyInjuries($engine->resume($state, $rng, 0, 900)->events));
        }

        return $hurt;
    };

    expect($count(0.0))->toBeGreaterThan($count(1.0));
});

it('puts strain on a man a committed challenge or a foul catches, and more when his legs have gone', function () {
    $injuries = new Injuries;
    [$standing, $slid, $fouled, $spentAndFouled] = [freshPlayer(), freshPlayer(), freshPlayer(), freshPlayer(0.0)];

    $injuries->tackled($standing, slide: false);
    $injuries->tackled($slid, slide: true);
    $injuries->tackled($fouled, slide: true, foul: true);
    $injuries->tackled($spentAndFouled, slide: true, foul: true);

    expect($standing->strain)->toBe(0.0)
        ->and($slid->strain)->toBeGreaterThan(0.0)
        ->and($fouled->strain)->toBeGreaterThan($slid->strain)
        ->and($spentAndFouled->strain)->toBeGreaterThan($fouled->strain);
});

it('degrades a hurt player until he is replaced', function () {
    $player = freshPlayer();
    $speed = $player->speed();

    $player->injured = true;
    $hurt = $player->speed();

    $replacement = PlayerState::fromSnapshot(PlayerState::substituted($player->toSnapshot(), [60, 60, 60, 60, 60, 60], 0.9));

    expect($hurt)->toBeLessThan($speed * 0.7)
        ->and($player->impairment())->toBe(1.0)
        ->and($replacement->injured)->toBeFalse()
        ->and($replacement->strain)->toBe(0.0)
        ->and($replacement->speed())->toBeGreaterThan($hurt);
});

it('keeps a hurt player on the pitch while a substitution is left to cover him', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [1, 0];
    $state->autoSubs = [false, false];
    makeCertain($state, 0, 5);
    makeCertain($state, 1, 5);

    $result = injuryRun($state, $rng, 0, 20);
    $events = $result->events;
    $mine = $result->state->players[PlayerState::id(0, 5)];
    $theirs = $result->state->players[PlayerState::id(1, 5)];

    expect(onlyInjuries($events))->toHaveCount(2)
        ->and($mine->injured)->toBeTrue()
        ->and($mine->retired)->toBeFalse()
        ->and($mine->isPlaying())->toBeTrue()
        ->and($theirs->retired)->toBeTrue()
        ->and($theirs->isPlaying())->toBeFalse()
        ->and(onlyInjuries($events, EventType::InjuryOff))->toHaveCount(1);
});

it('plays a side on a man down when it has no substitution left', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [0, 0];
    $state->autoSubs = [false, false];
    makeCertain($state, 0, 5);

    $result = injuryRun($state, $rng, 0, 600);
    $gone = $result->state->players[PlayerState::id(0, 5)];
    $parked = $gone->pos->toArray();

    expect(onlyInjuries($result->events, EventType::InjuryOff))->toHaveCount(1)
        ->and($gone->retired)->toBeTrue()
        ->and(count(array_filter($result->state->side(0), fn (PlayerState $p): bool => $p->isPlaying())))->toBe(10)
        ->and($parked[1] === 0.0 || $parked[1] === 1.0)->toBeTrue();

    $later = injuryRun($result->state, $rng, 600, 700);
    expect($later->state->players[PlayerState::id(0, 5)]->pos->toArray())->toBe($parked);
});

it('sends a limping player off once the last substitution is spent elsewhere', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [1, 0];
    $state->autoSubs = [false, false];
    makeCertain($state, 0, 5);

    $first = injuryRun($state, $rng, 0, 20);
    expect($first->state->players[PlayerState::id(0, 5)]->retired)->toBeFalse();

    $first->state->subsLeft = [0, 0];
    $second = injuryRun($first->state, $rng, 20, 21);

    expect($second->state->players[PlayerState::id(0, 5)]->retired)->toBeTrue();
});

it('handles two players hurt at once', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [1, 0];
    $state->autoSubs = [false, false];
    foreach ([3, 7] as $slot) {
        makeCertain($state, 0, $slot);
    }

    $first = injuryRun($state, $rng, 0, 5);

    expect(onlyInjuries($first->events))->toHaveCount(2)
        ->and(array_map(fn (MatchEvent $e): int => $e->minute, onlyInjuries($first->events)))->toBe([0, 0])
        ->and($first->state->hurt(0))->toBe([3 => 'limping', 7 => 'limping']);

    $first->state->subsLeft = [0, 0];
    $second = injuryRun($first->state, $rng, 5, 6);

    expect($second->state->hurt(0))->toBe([3 => 'off', 7 => 'off']);
});

it('does not hurt a man who is already hurt', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->autoSubs = [false, false];
    makeCertain($state, 0, 5);

    $first = injuryRun($state, $rng, 0, 20);
    $second = injuryRun($first->state, $rng, 20, 200);

    expect(onlyInjuries($first->events))->toHaveCount(1)
        ->and(array_filter(onlyInjuries($second->events), fn (MatchEvent $e): bool => $e->actorId === PlayerState::id(0, 5)))->toBe([]);
});

it('never hurts a goalkeeper, and hurts a man at most once', function () {
    $engine = new PositionalEngine;

    foreach (range(1, 6) as $seed) {
        [$state] = $engine->start(injuryTeam(), injuryTeam(), $seed);

        foreach ($state->players as $player) {
            expect($player->proneness === null)->toBe($player->isGoalkeeper());
        }

        $result = $engine->simulate(injuryTeam(), injuryTeam(), $seed);
        $seen = [];
        foreach (onlyInjuries($result->events) as $event) {
            expect($event->actorId % 100)->not->toBe(0)
                ->and($seen)->not->toHaveKey($event->actorId);
            $seen[$event->actorId] = true;
        }
    }
});

it('hurts nobody in a match saved before injuries existed', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $snapshot = $state->toSnapshot();

    foreach ($snapshot['players'] as &$player) {
        unset($player['pr'], $player['wk'], $player['sn'], $player['inj'], $player['ret']);
    }
    unset($player);
    unset($snapshot['subsLeft']);
    $old = PitchState::fromSnapshot($snapshot);

    expect($old->subsLeft)->toBe([0, 0]);

    foreach ($old->players as $player) {
        $player->stamina = 0.0;
    }

    expect(onlyInjuries($engine->resume($old, $rng, 0, 2000)->events))->toBe([]);
});

it('keeps injury state across a save and reload and resumes identically', function () {
    $engine = new PositionalEngine;
    $seed = null;
    foreach (range(1, 40) as $try) {
        $minutes = array_map(fn (MatchEvent $e): int => $e->minute, onlyInjuries($engine->simulate(injuryTeam(), injuryTeam(), $try)->events));
        if ($minutes !== [] && min($minutes) < 30) {
            $seed = $try;
            break;
        }
    }
    expect($seed)->not->toBeNull();

    $continuous = $engine->simulate(injuryTeam(), injuryTeam(), $seed);

    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), $seed);
    $first = $engine->resume($state, $rng, 0, 1500);
    $reloaded = PitchState::fromSnapshot(json_decode((string) json_encode($first->state?->toSnapshot()), true));
    $second = $engine->resume($reloaded, Rng::fromState($rng->stateValue()), 1500, $engine->totalTicks());

    expect([...$first->frames, ...$second->frames])->toEqual($continuous->frames)
        ->and([...$first->events, ...$second->events])->toEqual($continuous->events)
        ->and($second->state?->hurt(0))->toBe($continuous->state?->hurt(0))
        ->and($second->state?->hurt(1))->toBe($continuous->state?->hurt(1));
});

it('hurts a man in the very last tick', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    makeCertain($state, 0, 4);

    $final = $engine->totalTicks() - 1;
    $events = $engine->resume($state, $rng, $final, $final + 1)->events;

    expect(onlyInjuries($events))->toHaveCount(1)
        ->and(onlyInjuries($events)[0]->minute)->toBe(89);
});

it('hands the ball to a team-mate when the man who had it has to leave', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [0, 0];
    $state->autoSubs = [false, false];
    $carrierId = $state->carrierId;
    $state->players[$carrierId]->proneness = 0.5;
    $state->players[$carrierId]->strain = 1.0;

    $after = $engine->resume($state, $rng, 0, 1)->state;

    expect($after->players[$carrierId]->retired)->toBeTrue()
        ->and($after->carrierId)->not->toBe($carrierId)
        ->and($after->ballTo)->not->toBe($carrierId)
        ->and($after->carrier()?->isPlaying() ?? true)->toBeTrue();
});

it('bounds an injury between one and eight weeks, mostly short', function () {
    $weeks = array_map(fn (int $i): int => Injuries::weeks($i / 1000), range(0, 999));

    expect(min($weeks))->toBe(1)
        ->and(max($weeks))->toBe(Injuries::MAX_WEEKS)
        ->and(Injuries::weeks(0.99999))->toBe(Injuries::MAX_WEEKS)
        ->and(array_sum($weeks) / count($weeks))->toBeLessThan(4.0)
        ->and(count(array_filter($weeks, fn (int $w): bool => $w <= 2)))->toBeGreaterThanOrEqual(500);
});

it('gives every outfielder a bounded length of injury at kickoff', function () {
    $engine = new PositionalEngine;
    [$state] = $engine->start(injuryTeam(), injuryTeam(), 11);

    foreach ($state->players as $player) {
        if ($player->isGoalkeeper()) {
            continue;
        }

        expect($player->injuryWeeks)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(Injuries::MAX_WEEKS);
    }
});

it('puts an injury in the feed', function () {
    $commentary = new MatchCommentary;
    $hurt = new MatchEvent(70, EventType::Injury, 4, null, new Zone(2, 2), null, false, null, null);
    $off = new MatchEvent(70, EventType::InjuryOff, 4, null, new Zone(2, 2), null, false, null, null);

    expect($commentary->moment($hurt, [4 => 'Alice'])?->kind)->toBe('injury')
        ->and($commentary->moment($hurt, [4 => 'Alice'])?->text)->toContain('Alice')
        ->and($commentary->moment($off, [4 => 'Alice'])?->kind)->toBe('injury');
});

it('has a side that makes its own changes replace a hurt man at once, like for like', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    makeCertain($state, 1, 5);
    $before = $state->players[PlayerState::id(1, 5)]->attributes;

    $result = injuryRun($state, $rng, 0, 20);
    $man = $result->state->players[PlayerState::id(1, 5)];

    expect(onlyInjuries($result->events))->toHaveCount(1)
        ->and($man->injured)->toBeFalse()
        ->and($man->isPlaying())->toBeTrue()
        ->and($man->strain)->toBeLessThan(0.1)
        ->and($man->attributes)->toEqual($before)
        ->and($result->state->subsLeft[1])->toBe(4);
});

it('plays a self-managing side a man down once its changes are spent', function () {
    $engine = new PositionalEngine;
    [$state, $rng] = $engine->start(injuryTeam(), injuryTeam(), 4);
    $state->subsLeft = [5, 1];
    makeCertain($state, 1, 5);
    makeCertain($state, 1, 6);

    $result = injuryRun($state, $rng, 0, 20);

    expect($result->state->hurt(1))->toBe([6 => 'off'])
        ->and($result->state->players[PlayerState::id(1, 5)]->isPlaying())->toBeTrue();
});
