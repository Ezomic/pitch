<?php

declare(strict_types=1);

use App\Sim\Analysis\RealismHarness;
use App\Sim\Analysis\RealismReport;

/**
 * RealismReport has always known which of its ten metrics sit outside the range
 * a real match would fall in, but nothing failed when one did: the existing test
 * only checks that the ok flag agrees with the band it was computed from. That
 * is why the engine can be off on five metrics with a completely green suite.
 *
 * This is the ratchet. A metric inside its band has to stay inside it. A metric
 * outside its band may not drift further out. When one is fixed and comes back
 * into band, its entry here must be deleted, so the list can only ever shrink.
 */
/** Fixed batch size: the harness runs seeds 1..N, so the figures below are exact, not sampled. */
const REALISM_SEEDS = 40;

/**
 * How far outside its band each metric sits today, as measured at PITCH-145.
 * Delete an entry when its ticket lands; never raise one to make a run pass.
 *
 * @var array<string, float>
 */
const REALISM_KNOWN_OFF = [
    'Shots on target' => 2.10,      // 13.1 against a 7.5 to 11 band
    'Pass completion %' => 2.70,    // 67.3 against a 70 to 88 band, see PITCH-152
    'Crosses' => 3.30,              // 10.7 against a 14 to 42 band
    'Fouls' => 10.50,               // 7.5 against an 18 to 28 band, see PITCH-126 and PITCH-127
    'Final-third time %' => 10.10,  // 40.1 against a 12 to 30 band, see PITCH-152
];

/** How far outside its band a metric sits, or zero when it is inside. */
function bandDistance(float $value, float $low, float $high): float
{
    return match (true) {
        $value < $low => $low - $value,
        $value > $high => $value - $high,
        default => 0.0,
    };
}

/**
 * @return list<array{label: string, value: float, low: float, high: float, unit: string, ok: bool}>
 */
function realismRows(): array
{
    static $rows = null;

    return $rows ??= (new RealismReport((new RealismHarness)->run(REALISM_SEEDS)))->rows();
}

it('keeps every in-band metric inside its band', function () {
    $regressed = [];

    foreach (realismRows() as $row) {
        if (array_key_exists($row['label'], REALISM_KNOWN_OFF)) {
            continue;
        }

        if (! $row['ok']) {
            $regressed[] = sprintf(
                '%s is %.1f%s, outside its %s to %s%s band',
                $row['label'], $row['value'], $row['unit'], $row['low'], $row['high'], $row['unit'],
            );
        }
    }

    expect($regressed)->toBe([], "The engine has drifted out of a band it used to meet:\n- ".implode("\n- ", $regressed));
});

it('never lets a known-off metric drift further out', function () {
    $worse = [];

    foreach (realismRows() as $row) {
        $tolerated = REALISM_KNOWN_OFF[$row['label']] ?? null;

        if ($tolerated === null) {
            continue;
        }

        $distance = bandDistance($row['value'], $row['low'], $row['high']);

        // A hair of slack, so a change that only shifts the last decimal place
        // of a rounded figure does not read as a regression.
        if ($distance > $tolerated + 0.05) {
            $worse[] = sprintf(
                '%s is now %.2f outside its band, was %.2f',
                $row['label'], $distance, $tolerated,
            );
        }
    }

    expect($worse)->toBe([], "A known-off metric got worse:\n- ".implode("\n- ", $worse));
});

it('requires a fixed metric to be removed from the known-off list', function () {
    $fixed = [];

    foreach (realismRows() as $row) {
        if (! array_key_exists($row['label'], REALISM_KNOWN_OFF)) {
            continue;
        }

        if (bandDistance($row['value'], $row['low'], $row['high']) === 0.0) {
            $fixed[] = $row['label'];
        }
    }

    expect($fixed)->toBe([], sprintf(
        'These metrics are back inside their band. Delete them from REALISM_KNOWN_OFF: %s',
        implode(', ', $fixed),
    ));
});

it('lists every known-off entry against a metric that exists', function () {
    $labels = array_column(realismRows(), 'label');

    foreach (array_keys(REALISM_KNOWN_OFF) as $label) {
        expect($labels)->toContain($label);
    }
});
