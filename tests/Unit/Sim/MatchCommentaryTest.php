<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchCommentary;
use App\Sim\Squad\MatchNarrator;

function throughBall(int $actorId, int $targetId): MatchEvent
{
    return new MatchEvent(
        minute: 30,
        type: EventType::Pass,
        actorId: $actorId,
        targetId: $targetId,
        from: new Zone(3, 2),
        to: new Zone(5, 2),
        success: true,
        decision: null,
        roll: null,
    );
}

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

it('does not repeat the label when the opposition passes among itself', function () {
    $names = [101 => 'Opposition', 102 => 'Opposition'];

    $moment = (new MatchCommentary)->moment(throughBall(101, 102), $names);

    expect($moment)->not->toBeNull()
        ->and($moment->kind)->toBe('chance')
        ->and(substr_count($moment->text, 'Opposition'))->toBe(1)
        ->and($moment->text)->not->toContain('Opposition Opposition');
});

it('still names both players for a home through ball', function () {
    $names = [1 => 'Alice', 2 => 'Bob'];

    $moment = (new MatchCommentary)->moment(throughBall(1, 2), $names);

    expect($moment->text)->toContain('Alice')->toContain('Bob');
});

it('produces the same line for the same event, wherever it appears', function () {
    $names = [101 => 'Opposition', 102 => 'Opposition'];
    $commentary = new MatchCommentary;

    // The line is now a function of the event alone, so it no longer depends on
    // where in a generated batch the event happened to sit.
    $first = $commentary->moment(throughBall(101, 102), $names);
    $second = $commentary->moment(throughBall(101, 102), $names);

    expect($first->text)->toBe($second->text);
});

it('drops the duplicated receiver from a 2D caption', function () {
    $caption = (new MatchCommentary)->label(
        EventType::Cross, true, false, 'Opposition', 'Opposition', 7,
    );

    expect(substr_count($caption, 'Opposition'))->toBe(1);
});
