<?php

declare(strict_types=1);

use App\Sim\Domain\EventType;
use App\Sim\Domain\Zone;
use App\Sim\Engine\DecisionMaker;
use App\Sim\Engine\Option;
use App\Sim\Engine\Rng;

function option(float $threat): Option
{
    return new Option(EventType::Pass, new Zone(2, 1), 1, $threat);
}

it('maps vision to the number of options a player can evaluate', function () {
    $maker = new DecisionMaker;

    expect($maker->visibleCount(30, 10))->toBe(2)
        ->and($maker->visibleCount(80, 10))->toBe(5)
        ->and($maker->visibleCount(5, 10))->toBe(1)
        ->and($maker->visibleCount(300, 4))->toBe(4);
});

it('lets a higher-vision player choose at least as well as a lower-vision player', function () {
    $maker = new DecisionMaker;
    $options = array_map(fn (int $i) => option($i / 10), range(1, 10));

    $low = $maker->decide($options, 30, new Rng(7));
    $high = $maker->decide($options, 80, new Rng(7));

    expect($high->decision->chosenThreat)
        ->toBeGreaterThanOrEqual($low->decision->chosenThreat);
    expect($high->decision->optionsVisible)
        ->toBeGreaterThan($low->decision->optionsVisible);
});

it('reports the true best available threat regardless of vision', function () {
    $maker = new DecisionMaker;
    $options = array_map(fn (int $i) => option($i / 10), range(1, 10));

    $choice = $maker->decide($options, 30, new Rng(7));

    expect($choice->decision->bestAvailableThreat)->toBe(1.0)
        ->and($choice->decision->gap())->toBeGreaterThanOrEqual(0.0);
});
