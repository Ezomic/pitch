<?php

declare(strict_types=1);

use App\Sim\Analysis\ShapeHarness;

it('averages positions deterministically and on the pitch', function () {
    $harness = new ShapeHarness;

    $a = $harness->run(4);
    $b = $harness->run(4);

    expect($a)->toEqual($b)->and($a->matches)->toBe(4);

    // Every mean position sits on the pitch.
    foreach ($a->positions as [$x, $y]) {
        expect($x)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0)
            ->and($y)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }

    // Keepers sit deep in opposite goals: home defends x≈0, away x≈1.
    expect($a->side(0)[0][0])->toBeLessThan(0.2)
        ->and($a->side(1)[0][0])->toBeGreaterThan(0.8);
});

it('derives a sane, mirrored team shape', function () {
    $shape = (new ShapeHarness)->run(6);

    foreach ([0, 1] as $side) {
        $m = $shape->shape($side);

        expect($m['lineHeight'])->toBeGreaterThan(0.0)->toBeLessThan(1.0)
            ->and($m['length'])->toBeGreaterThan(0.0)
            ->and($m['width'])->toBeGreaterThan(0.0)->toBeLessThanOrEqual(1.0);
    }

    // Evenly matched sides settle into mirror-image shapes.
    expect(abs($shape->shape(0)['lineHeight'] - $shape->shape(1)['lineHeight']))->toBeLessThan(0.1);
});
