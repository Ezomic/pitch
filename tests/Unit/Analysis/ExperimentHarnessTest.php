<?php

declare(strict_types=1);

use App\Sim\Analysis\ExperimentHarness;

it('isolates one attribute deterministically', function () {
    $harness = new ExperimentHarness;

    $a = $harness->run('passing', 40, 6);
    $b = $harness->run('passing', 40, 6);

    // Same seeds both arms, both runs: the result is reproducible.
    expect($a['control'])->toEqual($b['control'])
        ->and($a['treatment'])->toEqual($b['treatment']);

    // Raising passing lifts the home side's completion rate, cleanly.
    $control = $a['control']->passesCompleted / max(1, $a['control']->passes);
    $treatment = $a['treatment']->passesCompleted / max(1, $a['treatment']->passes);

    expect($treatment)->toBeGreaterThan($control);
});

it('sharpens finishing into conversion', function () {
    // Conversion is noisy match to match, so this needs a batch big enough for the
    // effect to clear the noise; a handful of seeds flips on chance alone.
    $result = (new ExperimentHarness)->run('finishing', 40, 20);

    $control = $result['control']->goalsFor / max(1, $result['control']->shots);
    $treatment = $result['treatment']->goalsFor / max(1, $result['treatment']->shots);

    expect($treatment)->toBeGreaterThan($control);
});

it('rejects an unknown attribute', function () {
    expect(fn () => (new ExperimentHarness)->run('height', 10, 1))
        ->toThrow(InvalidArgumentException::class);
});
