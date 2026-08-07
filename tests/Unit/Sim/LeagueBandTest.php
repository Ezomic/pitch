<?php

declare(strict_types=1);

use App\Sim\Analysis\LeagueHarness;
use App\Sim\Analysis\LeagueReport;

/**
 * The same ratchet PITCH-145 put on the positional engine, for the league.
 *
 * The auto-resolved league runs down a different path entirely (FixtureResolver
 * over MatchEngine) and had no check of its own, so nobody could say whether a
 * change to the resolver made league tables more or less plausible. PITCH-151
 * was calibrated by hand against an ad-hoc set of clubs, and PITCH-155 was
 * filed on a scoring figure measured against that same unrepresentative set.
 * This is the fixed, realistic division that settles such questions.
 */
/** Fixed batch: the harness runs seeds 1..N over a fixed division, so figures are exact. */
const LEAGUE_SEEDS = 40;

/**
 * How far outside its band each metric sits today. Delete an entry when it is
 * fixed, tighten it when a change brings it closer, never raise it.
 *
 * @var array<string, float>
 */
const LEAGUE_KNOWN_OFF = [
    // Roughly one match in seven finishes nil-nil, against nearer one in twelve
    // in a real division. The resolver has no notion of the scoreline while a
    // match runs, so a goalless game never opens up the way a real one does.
    'Goalless %' => 0.80, // 14.8% against a 4 to 14% band, over the 40-seed batch
];

/**
 * @return list<array{label: string, value: float, low: float, high: float, unit: string, ok: bool}>
 */
function leagueRows(): array
{
    static $rows = null;

    return $rows ??= (new LeagueReport((new LeagueHarness)->run(LEAGUE_SEEDS)))->rows();
}

function leagueBandDistance(float $value, float $low, float $high): float
{
    return match (true) {
        $value < $low => $low - $value,
        $value > $high => $value - $high,
        default => 0.0,
    };
}

it('keeps every in-band league metric inside its band', function () {
    $regressed = [];

    foreach (leagueRows() as $row) {
        if (array_key_exists($row['label'], LEAGUE_KNOWN_OFF) || $row['ok']) {
            continue;
        }

        $regressed[] = sprintf(
            '%s is %.1f%s, outside its %s to %s%s band',
            $row['label'], $row['value'], $row['unit'], $row['low'], $row['high'], $row['unit'],
        );
    }

    expect($regressed)->toBe([], "The league has drifted out of a band it used to meet:\n- ".implode("\n- ", $regressed));
});

it('never lets a known-off league metric drift further out', function () {
    $worse = [];

    foreach (leagueRows() as $row) {
        $tolerated = LEAGUE_KNOWN_OFF[$row['label']] ?? null;

        if ($tolerated === null) {
            continue;
        }

        $distance = leagueBandDistance($row['value'], $row['low'], $row['high']);

        if ($distance > $tolerated + 0.05) {
            $worse[] = sprintf('%s is now %.2f outside its band, was %.2f', $row['label'], $distance, $tolerated);
        }
    }

    expect($worse)->toBe([], "A known-off league metric got worse:\n- ".implode("\n- ", $worse));
});

it('requires a fixed league metric to be removed from the known-off list', function () {
    $fixed = [];

    foreach (leagueRows() as $row) {
        if (array_key_exists($row['label'], LEAGUE_KNOWN_OFF)
            && leagueBandDistance($row['value'], $row['low'], $row['high']) === 0.0) {
            $fixed[] = $row['label'];
        }
    }

    expect($fixed)->toBe([], sprintf(
        'These are back inside their band. Delete them from LEAGUE_KNOWN_OFF: %s',
        implode(', ', $fixed),
    ));
});

it('resolves the same division the same way every run', function () {
    expect((new LeagueHarness)->run(4))->toEqual((new LeagueHarness)->run(4));
});
