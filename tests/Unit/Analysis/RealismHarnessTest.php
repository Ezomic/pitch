<?php

declare(strict_types=1);

use App\Sim\Analysis\RealismHarness;
use App\Sim\Analysis\RealismReport;

it('computes consistent, deterministic match metrics', function () {
    $harness = new RealismHarness;

    $a = $harness->run(6);
    $b = $harness->run(6);

    // Same seeds, same totals: the batch is reproducible and safe to compare.
    expect($a)->toEqual($b)
        ->and($a->matches)->toBe(6)
        ->and($a->frames)->toBeGreaterThan(0)
        ->and($a->goals)->toBeGreaterThan(0)
        ->and($a->passesCompleted)->toBeLessThanOrEqual($a->passes)
        ->and($a->shotsOnTarget)->toBeLessThanOrEqual($a->shots)
        ->and($a->crossesCompleted)->toBeLessThanOrEqual($a->crosses)
        ->and($a->framesHome)->toBeLessThanOrEqual($a->frames);
});

it('sees even possession for evenly matched sides', function () {
    $rows = collect((new RealismReport((new RealismHarness)->run(10)))->rows())
        ->keyBy('label');

    expect($rows['Home possession %']['value'])->toBeGreaterThan(40.0)->toBeLessThan(60.0);
});

it('exposes every reference metric with a pass/fail band', function () {
    $rows = (new RealismReport((new RealismHarness)->run(4)))->rows();

    expect($rows)->toHaveCount(10);

    foreach ($rows as $row) {
        expect($row)->toHaveKeys(['label', 'value', 'low', 'high', 'unit', 'ok'])
            ->and($row['ok'])->toBe($row['value'] >= $row['low'] && $row['value'] <= $row['high']);
    }
});
