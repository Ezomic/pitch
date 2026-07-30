<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;

/** @return array{int, int, int} headers, blocks, headed goals */
function headerBlockCounts(int $seeds): array
{
    $engine = new PositionalEngine;
    $headers = 0;
    $blocks = 0;
    $headedGoals = 0;

    foreach (range(1, $seeds) as $seed) {
        $result = $engine->simulate(
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            Roster::build(new Attributes(72, 72, 72, 72, 72, 72)),
            $seed,
        );

        foreach ($result->events as $event) {
            if ($event->type === EventType::Header) {
                $headers++;
                $event->success && $headedGoals++;
            }

            if ($event->type === EventType::Block) {
                $blocks++;
            }
        }
    }

    return [$headers, $blocks, $headedGoals];
}

it('heads crosses at goal and blocks shots', function () {
    [$headers, $blocks] = headerBlockCounts(12);

    // Both were defined but never emitted before; a batch must now produce them.
    expect($headers)->toBeGreaterThan(0)
        ->and($blocks)->toBeGreaterThan(0);
});

it('scores headed goals, but rarely', function () {
    [$headers, , $headedGoals] = headerBlockCounts(12);

    // A header is a real route to goal and a harder finish than a foot shot.
    expect($headedGoals)->toBeLessThan($headers);
});

it('records headers as shots so they count toward the shot tally', function () {
    expect(EventType::Header->isShot())->toBeTrue()
        ->and(EventType::Block->isShot())->toBeFalse();
});
