<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchLineups;
use App\Sim\Squad\MatchTimeline;
use App\Sim\Squad\PlayerMotion;

function motionLineups(): array
{
    $names = [];
    foreach (Formation::balanced()->slots() as $slot) {
        $names[$slot] = "P{$slot}";
    }

    return (new MatchLineups)->build(Formation::balanced(), Formation::balanced(), $names);
}

function motionFrame(int $side, float $x1, float $y1, float $x2, float $y2, string $type = 'pass'): array
{
    return ['m' => 10, 's' => $side, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 't' => $type];
}

it('places the ball carrier exactly on the ball', function () {
    $lineups = motionLineups();
    $frame = motionFrame(0, 0.35, 0.5, 0.7, 0.5);

    $positions = (new PlayerMotion)->build([$frame], $lineups)[0];
    $carrier = $positions['p'][$positions['b']];

    // The carrier snaps to where the ball started the frame.
    expect($carrier[0])->toBe(0.35)
        ->and($carrier[1])->toBe(0.5)
        // ...and belongs to the side in possession.
        ->and($lineups[$positions['b']]['s'])->toBe(0);
});

it('pushes the team in possession up toward an advanced ball', function () {
    $lineups = motionLineups();
    // Home attacks left to right; a ball high up the pitch should pull the home
    // outfield forward past its resting shape.
    $positions = (new PlayerMotion)->build([motionFrame(0, 0.75, 0.5, 0.85, 0.5)], $lineups)[0];

    $restAvg = avgX($lineups, 0);
    $liveAvg = avgLiveX($lineups, $positions, 0);

    expect($liveAvg)->toBeGreaterThan($restAvg);
});

it('keeps every coordinate on the pitch', function () {
    $engine = new MatchEngine;
    $players = Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
    $home = $engine->simulate($players, 7);
    $away = $engine->simulate($players, 11);
    $timeline = (new MatchTimeline)->build($home, $away, []);
    $lineups = motionLineups();

    foreach ((new PlayerMotion)->build($timeline, $lineups) as $frame) {
        expect($frame['p'])->toHaveCount(count($lineups));
        foreach ($frame['p'] as [$x, $y]) {
            expect($x)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0)
                ->and($y)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});

it('is deterministic for the same timeline', function () {
    $engine = new MatchEngine;
    $players = Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
    $timeline = (new MatchTimeline)->build($engine->simulate($players, 7), $engine->simulate($players, 11), []);
    $lineups = motionLineups();

    $motion = new PlayerMotion;

    expect($motion->build($timeline, $lineups))->toBe($motion->build($timeline, $lineups));
});

function avgX(array $lineups, int $side): float
{
    $xs = [];
    foreach ($lineups as $p) {
        if ($p['s'] === $side && ! $p['gk']) {
            $xs[] = $p['x'];
        }
    }

    return array_sum($xs) / count($xs);
}

function avgLiveX(array $lineups, array $positions, int $side): float
{
    $xs = [];
    foreach ($lineups as $i => $p) {
        if ($p['s'] === $side && ! $p['gk']) {
            $xs[] = $positions['p'][$i][0];
        }
    }

    return array_sum($xs) / count($xs);
}
