<?php

declare(strict_types=1);

use App\Sim\Analysis\MatchMetrics;
use App\Sim\Analysis\RealismBaseline;
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
/** Fixed batch size: the harness runs seeds 1..N, and the recorded baseline holds the same N. */
const REALISM_SEEDS = RealismBaseline::SEEDS;

/** How many standard errors a known-off metric may move outwards before the run counts as worse. */
const REALISM_SIGMAS = 3.0;

/**
 * How far outside its band each metric sits, recorded from the per-seed baseline
 * in tests/Fixtures/realism-baseline.json (php artisan pitch:realism-baseline).
 * Delete an entry when its ticket lands, and tighten one whenever a change
 * brings a metric closer in. Never raise one to make a run pass: these figures
 * are re-measured from a fresh baseline, not edited, and the test below fails
 * when an entry and the baseline disagree.
 *
 * @var array<string, float>
 */
const REALISM_KNOWN_OFF = [
    'Shots on target' => 1.40,   // 12.4 against a 7.5 to 11 band
    'Crosses' => 0.10,           // 13.9 against a 14 to 42 band
    'Fouls' => 6.40,             // 11.6 against an 18 to 28 band, see PITCH-126
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
 * @return list<MatchMetrics>
 */
function realismSeeds(): array
{
    static $perSeed = null;

    return $perSeed ??= (new RealismHarness)->perSeed(REALISM_SEEDS);
}

/**
 * @return list<array{label: string, value: float, low: float, high: float, unit: string, ok: bool}>
 */
function realismRows(): array
{
    static $rows = null;

    return $rows ??= (new RealismReport(MatchMetrics::sum(realismSeeds())))->rows();
}

/**
 * How far a known-off metric has moved outwards from the recorded baseline over
 * the same seeds (negative when it moved in), with the standard error of that
 * move. The same seeds are played before and after, so a change that only
 * re-draws part of a match cancels out and a real shift does not.
 *
 * @param  array{label: string, value: float, low: float, high: float, unit: string, ok: bool}  $row
 * @return array{outwards: float, error: float}
 */
function realismDrift(RealismBaseline $baseline, array $row): array
{
    ['delta' => $delta, 'error' => $error] = $baseline->shift($row['label'], realismSeeds());

    return ['outwards' => $baseline->value($row['label']) > $row['high'] ? $delta : -$delta, 'error' => $error];
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
    $baseline = RealismBaseline::recorded();
    $worse = [];

    foreach (realismRows() as $row) {
        if (! array_key_exists($row['label'], REALISM_KNOWN_OFF)) {
            continue;
        }

        ['outwards' => $outwards, 'error' => $error] = realismDrift($baseline, $row);

        if ($outwards > REALISM_SIGMAS * $error) {
            $worse[] = sprintf(
                '%s moved %.2f further outside its band, %.1f standard errors of %.2f, over the same %d seeds as the baseline',
                $row['label'], $outwards, $error > 0.0 ? $outwards / $error : INF, $error, REALISM_SEEDS,
            );
        }
    }

    expect($worse)->toBe([], "A known-off metric got worse:\n- ".implode("\n- ", $worse));
});

it('records every known-off figure from the baseline', function () {
    $baseline = RealismBaseline::recorded();

    expect($baseline->seeds())->toBe(REALISM_SEEDS, 'Re-record with: php artisan pitch:realism-baseline --write');

    foreach (realismRows() as $row) {
        if (! array_key_exists($row['label'], REALISM_KNOWN_OFF)) {
            continue;
        }

        expect(REALISM_KNOWN_OFF[$row['label']])->toEqualWithDelta(
            bandDistance(round($baseline->value($row['label']), 1), $row['low'], $row['high']),
            0.001,
            "{$row['label']} no longer matches the recorded baseline",
        );
    }
});

it('requires a fixed metric to be removed from the known-off list', function () {
    $baseline = RealismBaseline::recorded();
    $fixed = [];

    foreach (realismRows() as $row) {
        if (! array_key_exists($row['label'], REALISM_KNOWN_OFF)) {
            continue;
        }

        // Sitting inside the band is not enough: a metric a hair outside it
        // crosses over on a re-draw alone. It has to have moved in, measurably.
        ['outwards' => $outwards, 'error' => $error] = realismDrift($baseline, $row);

        if (bandDistance($row['value'], $row['low'], $row['high']) === 0.0 && -$outwards > REALISM_SIGMAS * $error) {
            $fixed[] = $row['label'];
        }
    }

    expect($fixed)->toBe([], sprintf(
        'These metrics are back inside their band. Delete them from REALISM_KNOWN_OFF and re-record the baseline: %s',
        implode(', ', $fixed),
    ));
});

it('lists every known-off entry against a metric that exists', function () {
    $labels = array_column(realismRows(), 'label');

    foreach (array_keys(REALISM_KNOWN_OFF) as $label) {
        expect($labels)->toContain($label);
    }
});
