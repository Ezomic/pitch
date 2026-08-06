<?php

declare(strict_types=1);

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;

function sideRated(int $base, string $formationId = '433', ?Mentality $mentality = null): TeamSetup
{
    $formation = Formation::fromId($formationId);

    $bySlot = [];
    foreach ($formation->slots() as $slot) {
        $bySlot[$slot] = new Attributes(
            $base + ($slot % 5),
            $base + ($slot % 3),
            $base - ($slot % 4),
            $base + ($slot % 7) - 3,
            $base - ($slot % 6) + 2,
            $base + ($slot % 2),
        );
    }

    return new TeamSetup($bySlot, $formation, $mentality ?? Mentality::Balanced, $base + 3, $base - 2);
}

/**
 * @return array{home: int, draw: int, away: int}
 */
function leagueSplit(int $seeds = 60): array
{
    $resolver = new FixtureResolver;
    $ratings = [50, 55, 58, 62, 65, 72];
    $formations = ['442', '433', '352', '4231', '532', '343'];
    $mentalities = [Mentality::Balanced, Mentality::Attacking, Mentality::Defensive];

    $teams = [];
    foreach ($ratings as $i => $rating) {
        $teams[] = sideRated($rating, $formations[$i % count($formations)], $mentalities[$i % 3]);
    }

    $split = ['home' => 0, 'draw' => 0, 'away' => 0];

    foreach ($teams as $i => $home) {
        foreach ($teams as $j => $away) {
            if ($i === $j) {
                continue;
            }

            for ($seed = 1; $seed <= $seeds; $seed++) {
                $result = $resolver->resolve($home, $away, $seed * 37 + $i * 5 + $j);

                $key = match (true) {
                    $result['home'] > $result['away'] => 'home',
                    $result['home'] < $result['away'] => 'away',
                    default => 'draw',
                };
                $split[$key]++;
            }
        }
    }

    return $split;
}

it('makes playing at home worth something', function () {
    $split = leagueSplit();
    $total = array_sum($split);

    $home = $split['home'] / $total * 100;
    $away = $split['away'] / $total * 100;

    // Home and away used to be within a point of each other, because the resolver
    // treated the two ends of the fixture identically.
    expect($home)->toBeGreaterThan($away + 8.0)
        ->and($home)->toBeGreaterThan(38.0)
        ->and($home)->toBeLessThan(50.0);
});

it('does not turn every match into a draw', function () {
    $split = leagueSplit();
    $draws = $split['draw'] / array_sum($split) * 100;

    expect($draws)->toBeLessThan(33.0);
});

it('gives two identical sides a real match rather than a draw', function () {
    $resolver = new FixtureResolver;
    $side = sideRated(58);

    $draws = 0;
    for ($seed = 1; $seed <= 60; $seed++) {
        $result = $resolver->resolve($side, $side, $seed * 13);

        if ($result['home'] === $result['away']) {
            $draws++;
        }
    }

    // Both attacks ran from the same seed, so the same numbers drove both ends
    // of the pitch and identical setups drew on 93% of seeds.
    expect($draws / 60 * 100)->toBeLessThan(50.0);
});

it('still resolves a fixture the same way every time', function () {
    $resolver = new FixtureResolver;
    $home = sideRated(64, '442');
    $away = sideRated(57, '532', Mentality::Defensive);

    foreach ([1, 99, 123_456] as $seed) {
        expect($resolver->resolve($home, $away, $seed))
            ->toBe($resolver->resolve($home, $away, $seed));
    }
});
