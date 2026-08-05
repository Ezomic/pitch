<?php

declare(strict_types=1);

use App\Sim\Analysis\GoldenMaster;

/**
 * Simulating every seed takes a few seconds, so the run is shared across the
 * assertions below rather than repeated for each.
 *
 * @return array<string, string>
 */
function goldenMasterDigests(): array
{
    static $digests = null;

    return $digests ??= (new GoldenMaster)->digests();
}

it('still produces the recorded output for every seed', function () {
    $master = new GoldenMaster;
    $recorded = $master->recorded();

    expect($recorded)->not->toBe([], 'No golden master recorded. Run: php artisan pitch:golden-master --write');

    $diverged = $master->diverged(goldenMasterDigests(), $recorded);

    expect($diverged)->toBe([], sprintf(
        "The engines no longer produce the recorded output for: %s.\n".
        'If that change was intended, re-record with: php artisan pitch:golden-master --write',
        implode(', ', $diverged),
    ));
});

it('fingerprints every engine path across the seed range', function () {
    $digests = goldenMasterDigests();

    expect($digests)->toHaveCount(GoldenMaster::SEEDS * 3);

    foreach (['positional', 'open-play', 'fixture'] as $path) {
        expect($digests)->toHaveKey("{$path}:1")
            ->and($digests)->toHaveKey($path.':'.GoldenMaster::SEEDS);
    }
});

it('gives a different fingerprint to different seeds', function () {
    $digests = goldenMasterDigests();

    // A path whose digest ignored the seed would silently pass the check above.
    expect($digests['positional:1'])->not->toBe($digests['positional:2'])
        ->and($digests['open-play:1'])->not->toBe($digests['open-play:2']);
});
