<?php

declare(strict_types=1);

use App\Sim\Experiment\PairedRunner;

it('separates vision across all three metrics over many paired matches', function () {
    $report = (new PairedRunner)->run(30, 80, 300, 1);

    expect($report->high->meanDecisionGap)->toBeLessThan($report->low->meanDecisionGap)
        ->and($report->high->progressivePassShare)->toBeGreaterThan($report->low->progressivePassShare)
        ->and($report->high->chancesPer90)->toBeGreaterThan($report->low->chancesPer90)
        ->and($report->separated())->toBeTrue();
});

it('is itself deterministic for a given base seed', function () {
    $a = (new PairedRunner)->run(30, 80, 50, 1);
    $b = (new PairedRunner)->run(30, 80, 50, 1);

    expect($a->high->chancesPer90)->toBe($b->high->chancesPer90)
        ->and($a->low->meanDecisionGap)->toBe($b->low->meanDecisionGap);
});

it('collects the requested number of sampled matches per arm', function () {
    $report = (new PairedRunner)->run(30, 80, 50, 1, sampleSize: 4);

    expect($report->samples)->toHaveCount(8);
});

it('populates both arm summaries with the match count', function () {
    $report = (new PairedRunner)->run(30, 80, 30, 1);

    expect($report->low->matches)->toBe(30)
        ->and($report->high->matches)->toBe(30);
});
