<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;

/**
 * Resolve a round-robin of league fixtures and sum what came of them.
 *
 * The positional engine has had a realism harness since PITCH-116, but the
 * auto-resolved league runs down a different path entirely (FixtureResolver
 * over MatchEngine) and had no check of its own. That is how it came to score
 * 1.79 goals a game against a real 2.7 without anything noticing.
 *
 * Deterministic: fixed sides, fixed seeds, so the same batch always yields the
 * same totals and a run is comparable against the last one.
 */
final class LeagueHarness
{
    /**
     * A division's worth of clubs, spread the way a real one is: a couple of
     * strong sides, a cluster in the middle, a couple who will struggle, each
     * with its own shape and temperament.
     *
     * @var list<array{int, string, string}> rating, formation, mentality
     */
    private const CLUBS = [
        [72, '433', 'attacking'],
        [68, '442', 'balanced'],
        [65, '4231', 'balanced'],
        [62, '352', 'attacking'],
        [58, '532', 'defensive'],
        [54, '343', 'balanced'],
    ];

    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
    ) {}

    /** Every club plays every other home and away, over $seeds repeats. */
    public function run(int $seeds = 60): LeagueMetrics
    {
        $clubs = array_map(
            fn (array $club): TeamSetup => $this->club($club[0], $club[1], Mentality::from($club[2])),
            self::CLUBS,
        );

        $metrics = LeagueMetrics::zero();

        foreach ($clubs as $i => $home) {
            foreach ($clubs as $j => $away) {
                if ($i === $j) {
                    continue;
                }

                for ($seed = 1; $seed <= $seeds; $seed++) {
                    $result = $this->resolver->resolve($home, $away, $seed * 37 + $i * 5 + $j);
                    $metrics = $metrics->add($result['home'], $result['away']);
                }
            }
        }

        return $metrics;
    }

    /** A side whose players vary around a base rating rather than being clones. */
    private function club(int $base, string $formationId, Mentality $mentality): TeamSetup
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

        return new TeamSetup($bySlot, $formation, $mentality, $base + 3, $base - 2);
    }
}
