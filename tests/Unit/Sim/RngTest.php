<?php

declare(strict_types=1);

use App\Sim\Engine\Rng;

it('produces an identical stream for the same seed', function () {
    $a = new Rng(1234);
    $b = new Rng(1234);

    $streamA = array_map(fn () => $a->next(), range(1, 50));
    $streamB = array_map(fn () => $b->next(), range(1, 50));

    expect($streamA)->toBe($streamB);
});

it('produces a different stream for a different seed', function () {
    $a = array_map(fn () => (new Rng(1))->nextInt(), range(1, 1));
    $b = array_map(fn () => (new Rng(2))->nextInt(), range(1, 1));

    expect($a)->not->toBe($b);
});

it('keeps draws within the unit interval', function () {
    $rng = new Rng(99);

    foreach (range(1, 1000) as $ignored) {
        $draw = $rng->next();
        expect($draw)->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
    }
});
