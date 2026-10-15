<?php

declare(strict_types=1);

use App\Sim\Analysis\MatchMetrics;
use App\Sim\Analysis\RealismBaseline;

function seedMetrics(int $shotsOnTarget, int $passes, int $passesCompleted, int $crosses, int $fouls): MatchMetrics
{
    return new MatchMetrics(1, 0, 0, $shotsOnTarget, $passes, $passesCompleted, $crosses, 0, $fouls, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0);
}

/**
 * @return list<MatchMetrics>
 */
function sampleSeeds(int $foulsOffset = 0, int $completedOffset = 0): array
{
    $seeds = [];

    foreach ([[10, 300, 210, 6, 12], [14, 320, 220, 9, 8], [12, 280, 200, 12, 15], [9, 310, 205, 7, 11]] as [$sot, $passes, $completed, $crosses, $fouls]) {
        $seeds[] = seedMetrics($sot, $passes, $completed + $completedOffset, $crosses, $fouls + $foulsOffset);
    }

    return $seeds;
}

it('reads no move and no noise when the same seeds come back unchanged', function () {
    $baseline = RealismBaseline::from(sampleSeeds());

    foreach (['Shots on target', 'Pass completion %', 'Crosses', 'Fouls'] as $label) {
        expect($baseline->shift($label, sampleSeeds()))->toEqualWithDelta(['delta' => 0.0, 'error' => 0.0], 1e-12);
    }
});

it('measures a uniform shift exactly, however much the seeds differ from each other', function () {
    $shift = RealismBaseline::from(sampleSeeds())->shift('Fouls', sampleSeeds(foulsOffset: -2));

    expect($shift['delta'])->toEqualWithDelta(-2.0, 1e-12)
        ->and($shift['error'])->toEqualWithDelta(0.0, 1e-12);
});

it('measures a percentage as a change in the pooled ratio', function () {
    $baseline = RealismBaseline::from(sampleSeeds());
    $shift = $baseline->shift('Pass completion %', sampleSeeds(completedOffset: 6));

    // 24 more completions over 1210 passes.
    expect($shift['delta'])->toEqualWithDelta(24 / 1210 * 100, 1e-9);
});

it('reports the standard error of the per-seed differences', function () {
    $moved = sampleSeeds();
    $moved[0] = seedMetrics(10, 300, 210, 6, 14);
    $moved[1] = seedMetrics(14, 320, 220, 9, 8);
    $moved[2] = seedMetrics(12, 280, 200, 12, 15);
    $moved[3] = seedMetrics(9, 310, 205, 7, 13);

    $shift = RealismBaseline::from(sampleSeeds())->shift('Fouls', $moved);

    // Differences 2, 0, 0, 2: mean 1, sample variance 4/3, standard error sqrt(1/3).
    expect($shift['delta'])->toEqualWithDelta(1.0, 1e-12)
        ->and($shift['error'])->toEqualWithDelta(sqrt(1 / 3), 1e-9);
});

it('gives the recorded value of a metric in the unit the report uses', function () {
    $baseline = RealismBaseline::from(sampleSeeds());

    expect($baseline->value('Fouls'))->toBe(11.5)
        ->and($baseline->value('Pass completion %'))->toEqualWithDelta(835 / 1210 * 100, 1e-9);
});

it('refuses to compare a different number of seeds', function () {
    RealismBaseline::from(sampleSeeds())->shift('Fouls', array_slice(sampleSeeds(), 0, 3));
})->throws(InvalidArgumentException::class);
