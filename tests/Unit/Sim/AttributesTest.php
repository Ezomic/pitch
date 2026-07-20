<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;

it('reports overall as the rounded mean of the six attributes', function () {
    expect((new Attributes(12, 12, 12, 12, 12, 12))->overall())->toBe(12)
        ->and((new Attributes(10, 11, 12, 13, 14, 15))->overall())->toBe(13) // 75/6 = 12.5 -> 13
        ->and((new Attributes(1, 1, 1, 1, 1, 1))->overall())->toBe(1)
        ->and((new Attributes(20, 20, 20, 20, 20, 20))->overall())->toBe(20);
});
