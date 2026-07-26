<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchNarrator;

function narratedReport(int $seed, int $opponentGoals = 2)
{
    $result = (new MatchEngine)->simulate(
        Roster::build(new Attributes(70, 70, 70, 70, 70, 70)),
        $seed,
    );

    $names = [];
    foreach (Roster::slots() as $slot) {
        $names[$slot] = "Name{$slot}";
    }

    return [$result, (new MatchNarrator)->narrate($result, $opponentGoals, $names)];
}

it('builds a scoreline and stats from the attack leg and opponent goals', function () {
    [$result, $report] = narratedReport(3, opponentGoals: 2);

    expect($report->homeGoals)->toBe($result->goals)
        ->and($report->awayGoals)->toBe(2)
        ->and($report->shots)->toBe($result->shots)
        ->and($report->passesCompleted)->toBe($result->passesCompleted)
        ->and($report->progressivePasses)->toBe($result->progressivePasses);
});

it('emits one goal moment per goal and orders moments by minute', function () {
    [$result, $report] = narratedReport(3);

    $goalMoments = array_filter($report->moments, fn ($m) => $m->kind === 'goal');
    expect($goalMoments)->toHaveCount($result->goals);

    $minutes = array_map(fn ($m) => $m->minute, $report->moments);
    $sorted = $minutes;
    sort($sorted);
    expect($minutes)->toBe($sorted);
});

it('resolves slot ids to player names in the commentary', function () {
    [, $report] = narratedReport(3);

    expect($report->moments)->not->toBeEmpty();

    $mentionsName = false;
    foreach ($report->moments as $moment) {
        if (str_contains($moment->text, 'Name')) {
            $mentionsName = true;
            break;
        }
    }

    expect($mentionsName)->toBeTrue();
});
