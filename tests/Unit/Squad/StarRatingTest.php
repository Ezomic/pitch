<?php

declare(strict_types=1);

use App\Sim\Squad\StarRating;

it('crowns the strongest club and floors the weakest', function () {
    $stars = (new StarRating)->rank(['a' => 80.0, 'b' => 60.0, 'c' => 40.0]);

    expect($stars['a'])->toBe(5.0)
        ->and($stars['c'])->toBe(1.0)
        ->and($stars['b'])->toBeGreaterThan(1.0)->toBeLessThan(5.0);
});

it('rates by standing, so one runaway club does not flatten the rest', function () {
    // The leader is far clear, but second place is still clearly second.
    $stars = (new StarRating)->rank(['runaway' => 99.0, 'second' => 50.0, 'third' => 49.0, 'last' => 48.0]);

    expect($stars['runaway'])->toBe(5.0)
        ->and($stars['second'])->toBeGreaterThan(3.0)
        ->and($stars['third'])->toBeGreaterThan($stars['last']);
});

it('gives clubs of equal strength the same stars', function () {
    $stars = (new StarRating)->rank(['a' => 70.0, 'b' => 70.0, 'c' => 50.0]);

    expect($stars['a'])->toBe($stars['b'])
        ->and($stars['a'])->toBeGreaterThan($stars['c']);
});

it('rates an evenly matched group in the middle rather than inventing a pecking order', function () {
    expect((new StarRating)->rank(['a' => 60.0, 'b' => 60.0]))->toBe(['a' => 3.0, 'b' => 3.0]);
});

it('only ever reports one to five stars, in halves', function () {
    $stars = (new StarRating)->rank(array_map(fn (int $i): float => $i * 3.7, range(1, 9)));

    foreach ($stars as $value) {
        expect($value)->toBeGreaterThanOrEqual(1.0)->toBeLessThanOrEqual(5.0)
            ->and(fmod($value * 2, 1.0))->toBe(0.0);
    }
});

it('handles an empty group', function () {
    expect((new StarRating)->rank([]))->toBe([]);
});
