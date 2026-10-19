<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\Position;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Rng;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\KickOff;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PlayerState;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Pitch\Vec2;

function staminaTeam(): array
{
    return Roster::build(new Attributes(72, 72, 72, 72, 72, 72));
}

function outfielders(PitchState $state): array
{
    return array_filter($state->players, fn (PlayerState $p): bool => ! $p->isGoalkeeper());
}

it('drains over a match and stays inside its bounds', function () {
    $engine = new PositionalEngine;
    $result = $engine->simulate(staminaTeam(), staminaTeam(), 3);
    $legs = array_map(fn (PlayerState $p): float => $p->stamina, outfielders($result->state));

    expect(array_sum($legs) / count($legs))->toBeLessThan(0.5)
        ->and(min($legs))->toBeGreaterThanOrEqual(0.0)
        ->and(max($legs))->toBeLessThanOrEqual(1.0);

    foreach ($result->state->players as $player) {
        if ($player->isGoalkeeper()) {
            expect($player->stamina)->toBe(1.0);
        }
    }
});

it('is visible in the match state', function () {
    $engine = new PositionalEngine;
    $result = $engine->simulate(staminaTeam(), staminaTeam(), 3);
    $energy = $result->state->energy(0);

    expect($energy)->toHaveCount(10)
        ->and(min($energy))->toBeLessThan(100);
});

it('slows a tired player and leaves a rested one alone', function () {
    $player = new PlayerState(1, 0, 1, Position::Midfielder, new Vec2(0.5, 0.5), new Attributes(70, 70, 70, 70, 70, 70));
    $fresh = $player->speed();

    $player->stamina = 0.6;
    expect($player->speed())->toBe($fresh);

    $player->stamina = 0.25;
    $tired = $player->speed();

    $player->stamina = 0.0;
    expect($tired)->toBeLessThan($fresh)
        ->and($player->speed())->toBeLessThan($tired);
});

it('makes a spent side complete fewer passes', function () {
    $engine = new PositionalEngine;
    $rate = function (float $stamina) use ($engine): float {
        $passes = 0;
        $completed = 0;

        foreach (range(1, 10) as $seed) {
            [$state, $rng] = $engine->start(staminaTeam(), staminaTeam(), $seed);
            foreach ($state->players as $player) {
                $player->stamina = $stamina;
            }

            foreach ($engine->resume($state, $rng, 0, 300)->events as $event) {
                if ($event->type === EventType::Pass) {
                    $passes++;
                    $completed += $event->success ? 1 : 0;
                }
            }
        }

        return $completed / max(1, $passes);
    };

    expect($rate(0.0))->toBeLessThan($rate(1.0));
});

it('starts a player with the reserve his season fitness gives him', function () {
    $fit = Roster::fromAttributes(array_fill_keys(Roster::slots(), new Attributes(60, 60, 60, 60, 60, 60)), Formation::balanced(), [1 => 55]);
    $states = (new KickOff)->buildStates($fit, $fit);

    expect($states[PlayerState::id(0, 1)]->stamina)->toBe(0.55)
        ->and($states[PlayerState::id(0, 2)]->stamina)->toBe(1.0)
        ->and(KickOff::startingStamina(140))->toBe(1.0)
        ->and(KickOff::startingStamina(-5))->toBe(0.0);
});

it('keeps tired legs tired across a save and reload', function () {
    $engine = new PositionalEngine;
    $continuous = $engine->simulate(staminaTeam(), staminaTeam(), 6);

    [$state, $rng] = $engine->start(staminaTeam(), staminaTeam(), 6);
    $first = $engine->resume($state, $rng, 0, 900);
    $reloaded = PitchState::fromSnapshot(json_decode((string) json_encode($first->state?->toSnapshot()), true));

    foreach ($first->state->players as $id => $player) {
        expect($reloaded->players[$id]->stamina)->toBe($player->stamina);
    }

    $second = $engine->resume($reloaded, Rng::fromState($rng->stateValue()), 900, $engine->totalTicks());

    expect([...$first->frames, ...$second->frames])->toEqual($continuous->frames)
        ->and($second->state?->energy(0))->toBe($continuous->state?->energy(0));
});

it('treats a saved player without stamina as fresh', function () {
    $engine = new PositionalEngine;
    [$state] = $engine->start(staminaTeam(), staminaTeam(), 1);
    $snapshot = $state->toSnapshot();

    foreach ($snapshot['players'] as &$player) {
        unset($player['st']);
    }
    unset($player);

    expect(PitchState::fromSnapshot($snapshot)->players[PlayerState::id(0, 3)]->stamina)->toBe(1.0);
});
