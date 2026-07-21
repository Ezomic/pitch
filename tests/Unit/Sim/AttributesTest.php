<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;

it('reports overall as the rounded mean of the six attributes', function () {
    expect((new Attributes(12, 12, 12, 12, 12, 12))->overall())->toBe(12)
        ->and((new Attributes(10, 11, 12, 13, 14, 15))->overall())->toBe(13) // 75/6 = 12.5 -> 13
        ->and((new Attributes(1, 1, 1, 1, 1, 1))->overall())->toBe(1)
        ->and((new Attributes(20, 20, 20, 20, 20, 20))->overall())->toBe(20);
});

it('leaves the attributes untouched when scaled by one', function () {
    $attrs = new Attributes(10, 11, 12, 13, 14, 15);

    expect($attrs->scaled(1.0))->toEqual($attrs);
});

it('scales every attribute by the factor', function () {
    $scaled = (new Attributes(10, 10, 10, 10, 10, 10))->scaled(0.7);

    expect($scaled->vision)->toBe(7)
        ->and($scaled->pace)->toBe(7);
});

it('clamps a scaled attribute to the 1..100 range', function () {
    $up = (new Attributes(95, 95, 95, 95, 95, 95))->scaled(1.15);
    $down = (new Attributes(1, 1, 1, 1, 1, 1))->scaled(0.5);

    expect($up->vision)->toBe(100) // 95 * 1.15 = 109.25 -> clamped to 100
        ->and($down->vision)->toBe(1); // 1 * 0.5 = 0.5 -> clamped to 1
});
