<?php

declare(strict_types=1);

use App\Sim\Engine\Formation;
use App\Sim\Squad\MatchLineups;

it('lays out both full teams with a keeper each', function () {
    $players = (new MatchLineups)->build(
        Formation::preset442(),
        Formation::preset433(),
        [1 => 'Left Back'],
    );

    $home = collect($players)->where('s', 0);
    $away = collect($players)->where('s', 1);

    expect($players)->toHaveCount(22)
        ->and($home)->toHaveCount(11)
        ->and($away)->toHaveCount(11)
        ->and($home->where('gk', true))->toHaveCount(1)
        ->and($away->where('gk', true))->toHaveCount(1);
});

it('names home players and leaves the opponent anonymous', function () {
    $players = (new MatchLineups)->build(Formation::preset442(), Formation::preset442(), [1 => 'Left Back']);

    $homeSlot1 = collect($players)->first(fn (array $p): bool => $p['s'] === 0 && $p['slot'] === 1);
    $awayOutfield = collect($players)->first(fn (array $p): bool => $p['s'] === 1 && ! $p['gk']);

    expect($homeSlot1['name'])->toBe('Left Back')
        ->and($awayOutfield['name'])->toBeNull();
});

it('mirrors the away side and puts keepers on their goal lines', function () {
    $players = (new MatchLineups)->build(Formation::preset442(), Formation::preset442(), []);

    $homeGk = collect($players)->first(fn (array $p): bool => $p['s'] === 0 && $p['gk']);
    $awayGk = collect($players)->first(fn (array $p): bool => $p['s'] === 1 && $p['gk']);

    expect($homeGk['x'])->toBe(0.0)
        ->and($homeGk['y'])->toBe(0.5)
        ->and($awayGk['x'])->toBe(1.0)
        ->and($awayGk['y'])->toBe(0.5);

    // Slot 1 is a defender at zone x=1 (0.2 normalised); the away counterpart mirrors to 0.8.
    $homeDef = collect($players)->first(fn (array $p): bool => $p['s'] === 0 && $p['slot'] === 1);
    $awayDef = collect($players)->first(fn (array $p): bool => $p['s'] === 1 && $p['slot'] === 1);

    expect($homeDef['x'])->toBe(0.2)
        ->and($awayDef['x'])->toBe(0.8);
});
