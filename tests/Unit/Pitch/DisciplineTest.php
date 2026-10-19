<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Vec2;
use App\Sim\Squad\MatchCommentary;

/**
 * @return list<MatchEvent>
 */
function disciplineEvents(int $seeds = 12): array
{
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    $events = [];
    for ($seed = 1; $seed <= $seeds; $seed++) {
        $events = [...$events, ...$engine->simulate($team(), $team(), $seed)->events];
    }

    return $events;
}

it('books players, and sends some off', function () {
    $events = disciplineEvents();

    $yellows = array_filter($events, fn (MatchEvent $e): bool => $e->type === EventType::YellowCard);
    $reds = array_filter($events, fn (MatchEvent $e): bool => $e->type === EventType::RedCard);

    expect($yellows)->not->toBeEmpty()
        ->and($reds)->not->toBeEmpty()
        // A sending off has to stay the exception, or a match stops being football.
        ->and(count($reds))->toBeLessThan(count($yellows));
});

it('never books the same player twice without sending him off', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    for ($seed = 1; $seed <= 8; $seed++) {
        $seen = [];

        foreach ($engine->simulate($team(), $team(), $seed)->events as $event) {
            if ($event->type !== EventType::YellowCard) {
                continue;
            }

            expect($seen)->not->toHaveKey((string) $event->actorId, 'A player was booked twice without a red.');
            $seen[$event->actorId] = true;
        }
    }
});

it('takes a sent-off player out of the match for good', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    $sentOff = 0;
    for ($seed = 1; $seed <= 12; $seed++) {
        $result = $engine->simulate($team(), $team(), $seed);
        $reds = array_filter($result->events, fn (MatchEvent $e): bool => $e->type === EventType::RedCard);

        foreach ($reds as $red) {
            $sentOff++;
            $player = $result->state?->players[$red->actorId] ?? null;

            expect($player)->not->toBeNull()
                ->and($player->sentOff)->toBeTrue()
                ->and($player->isPlaying())->toBeFalse();
        }
    }

    expect($sentOff)->toBeGreaterThan(0);
});

it('carries bookings and mentality through a goal', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    [$state, $rng] = $engine->start($team(), $team(), 5);
    $state->homeMentality = 'attacking';
    $state->awayMentality = 'defensive';
    $state->bookings[3] = 1;

    $result = $engine->resume($state, $rng, 0, $engine->totalTicks());

    // A kickoff builds a fresh state. Scoring used to reset the manager's
    // instruction to balanced, which is exactly when it matters most.
    expect($result->homeGoals + $result->awayGoals)->toBeGreaterThan(0)
        ->and($result->state?->homeMentality)->toBe('attacking')
        ->and($result->state?->awayMentality)->toBe('defensive')
        ->and($result->state?->bookings[3] ?? 0)->toBe(1);
});

it('keeps bookings across a save and reload', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    [$state] = $engine->start($team(), $team(), 2);
    $state->bookings[7] = 1;
    $state->players[7]->sentOff = true;

    $restored = PitchState::fromSnapshot(json_decode((string) json_encode($state->toSnapshot()), true));

    expect($restored->bookings[7] ?? 0)->toBe(1)
        ->and($restored->players[7]->sentOff)->toBeTrue();
});

it('shows a card as its own kind of moment in the feed', function () {
    $commentary = new MatchCommentary;
    $card = new MatchEvent(30, EventType::RedCard, 4, null, new Zone(2, 2), null, true, null, null);

    $moment = $commentary->moment($card, [4 => 'Alice']);

    expect($moment)->not->toBeNull()
        ->and($moment->kind)->toBe('card')
        ->and($moment->text)->toContain('Alice');
});

it('leaves a sent-off player on the touchline while his side plays on', function () {
    $engine = new PositionalEngine;
    $team = fn (): array => Roster::build(new Attributes(72, 72, 72, 72, 72, 72));

    [$state, $rng] = $engine->start($team(), $team(), 3);
    $state->players[4]->sentOff = true;
    $state->players[4]->pos = new Vec2(0.4, 0.0);

    $result = $engine->resume($state, $rng, 0, 600);
    $parked = $result->state?->players[4];

    expect($parked?->pos->toArray())->toBe([0.4, 0.0])
        ->and($result->state?->players[5]->pos->toArray())->not->toBe($result->state?->players[5]->anchor->toArray());
});
