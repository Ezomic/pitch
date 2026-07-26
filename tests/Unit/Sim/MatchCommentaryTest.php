<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchNarrator;

function feedTexts(int $seed): array
{
    $result = (new MatchEngine)->simulate(Roster::build(new Attributes(72, 72, 72, 72, 72, 72)), $seed);
    $names = [];
    foreach (Roster::slots() as $slot) {
        $names[$slot] = "P{$slot}";
    }

    return array_map(fn ($m) => $m->text, (new MatchNarrator)->feed($result, $names));
}

it('produces the same commentary for the same seed', function () {
    expect(feedTexts(7))->toBe(feedTexts(7));
});

it('varies the phrasing rather than repeating one line', function () {
    $texts = feedTexts(7);

    expect($texts)->not->toBeEmpty();

    // A healthy feed uses many distinct phrasings, not the same sentence over and over.
    $distinct = count(array_unique($texts));
    expect($distinct)->toBeGreaterThanOrEqual(8);
});

it('never leaves a name placeholder unfilled', function () {
    foreach (feedTexts(7) as $text) {
        expect($text)->not->toContain('{actor}')
            ->and($text)->not->toContain('{target}');
    }
});
