<?php

declare(strict_types=1);

use App\Sim\Domain\Zone;

it('raises threat as the ball advances toward the opponent goal', function () {
    for ($x = 0; $x < Zone::MAX_X; $x++) {
        expect((new Zone($x + 1, 1))->threat())
            ->toBeGreaterThan((new Zone($x, 1))->threat());
    }
});

it('rates the central lane at least as high as a wide lane at equal advancement', function () {
    foreach (range(0, Zone::MAX_X) as $x) {
        $central = (new Zone($x, 1))->threat();
        expect($central)->toBeGreaterThanOrEqual((new Zone($x, 0))->threat());
        expect($central)->toBeGreaterThanOrEqual((new Zone($x, 2))->threat());
    }
});

it('only allows shooting from advanced zones', function () {
    expect((new Zone(2, 1))->inShootingRange())->toBeFalse()
        ->and((new Zone(4, 1))->inShootingRange())->toBeTrue();
});
