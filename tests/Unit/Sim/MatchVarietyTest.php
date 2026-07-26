<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\Formation;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchTimeline;
use App\Sim\Squad\TeamSetup;

/**
 * The variety this suite guards was built across PITCH-61..65. These invariants
 * must hold as the engine evolves: a match should involve the whole team, show a
 * range of actions, and stay deterministic and realistic.
 */
const VARIETY_SEEDS = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];

function varietyMatch(int $seed): array
{
    return (new MatchEngine)
        ->simulate(Roster::build(new Attributes(72, 72, 72, 72, 72, 72)), $seed)
        ->events;
}

it('replays every seed byte-for-byte identically', function () {
    foreach (VARIETY_SEEDS as $seed) {
        $encode = fn (array $events) => json_encode(array_map(fn (MatchEvent $e) => $e->toArray(), $events));

        expect($encode(varietyMatch($seed)))->toBe($encode(varietyMatch($seed)));
    }
});

it('works the ball through most of the team every match', function () {
    foreach (VARIETY_SEEDS as $seed) {
        $carriers = [];
        foreach (varietyMatch($seed) as $event) {
            if ($event->actorId !== MatchEngine::DEFENDER_ID) {
                $carriers[$event->actorId] = true;
            }
        }

        expect(count($carriers))->toBeGreaterThanOrEqual(8);
    }
});

it('shows a full range of actions across a run of matches', function () {
    $seen = [];
    foreach (VARIETY_SEEDS as $seed) {
        foreach (varietyMatch($seed) as $event) {
            $seen[$event->type->value] = ($seen[$event->type->value] ?? 0) + 1;
        }
    }

    $expected = ['pass', 'dribble', 'shot', 'interception', 'tackle', 'clearance',
        'cross', 'header', 'save', 'block', 'foul', 'corner'];

    foreach ($expected as $type) {
        expect($seen)->toHaveKey($type);
    }

    // Passing is the backbone of play: it must dominate the event mix.
    expect($seen['pass'])->toBeGreaterThan($seen['shot'] + $seen['header']);
});

it('keeps possessions bounded and scorelines sane', function () {
    foreach (VARIETY_SEEDS as $seed) {
        $events = varietyMatch($seed);
        $goals = 0;
        foreach ($events as $event) {
            if ($event->type->isShot() && $event->success) {
                $goals++;
            }
        }

        // 60 possessions, at most 16 ticks each plus a follow-up: comfortably under 2500.
        expect(count($events))->toBeLessThan(2500)
            ->and($goals)->toBeLessThanOrEqual(12);
    }
});

it('stays realistic against a proper defence', function () {
    $engine = new MatchEngine;
    $attack = Roster::fromAttributes(array_fill_keys(range(1, 10), new Attributes(62, 62, 62, 62, 62, 62)), Formation::balanced());
    $defence = TeamSetup::baseline()->defence();

    $total = 0;
    foreach (VARIETY_SEEDS as $seed) {
        $total += $engine->simulate($attack, $seed, $defence, Formation::balanced(), 1.0)->goals;
    }

    // A leg should average roughly one goal, not a cricket score or a shutout every time.
    $average = $total / count(VARIETY_SEEDS);
    expect($average)->toBeGreaterThan(0.3)->toBeLessThan(3.0);
});

it('matches the 2D timeline goal frames to the scoreline', function () {
    $engine = new MatchEngine;
    $players = Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    foreach ([[1, 2], [8, 13], [34, 55]] as [$homeSeed, $awaySeed]) {
        $home = $engine->simulate($players, $homeSeed);
        $away = $engine->simulate($players, $awaySeed);
        $frames = (new MatchTimeline)->build($home, $away, []);

        $homeGoals = count(array_filter($frames, fn ($f) => $f['s'] === 0 && $f['goal']));
        $awayGoals = count(array_filter($frames, fn ($f) => $f['s'] === 1 && $f['goal']));

        expect($homeGoals)->toBe($home->goals)
            ->and($awayGoals)->toBe($away->goals);
    }
});
