<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Player;

final class MatchEngine
{
    private const int POSSESSIONS_PER_MATCH = 60;

    private const int MAX_TICKS_PER_POSSESSION = 16;

    private const int MATCH_MINUTES = 90;

    public function __construct(
        private readonly OptionBuilder $optionBuilder = new OptionBuilder,
        private readonly DecisionMaker $decisionMaker = new DecisionMaker,
        private readonly Resolver $resolver = new Resolver,
    ) {}

    /**
     * Simulate one match for a single attacking side against a defending team.
     * Same players + seed + defense + formation + attackBias always produce the
     * identical event log. With no defense the attack meets only zone pressure;
     * a null formation and attackBias 1.0 reproduce the original behaviour.
     *
     * @param  array<int, Player>  $players  keyed by player id
     */
    public function simulate(array $players, int $seed, ?Defense $defense = null, ?Formation $formation = null, float $attackBias = 1.0, int $fromMinute = 0, int $toMinute = self::MATCH_MINUTES): MatchResult
    {
        $defense ??= Defense::none();
        $formation ??= Formation::balanced();
        $bands = $this->startBands($players);
        $rng = new Rng($seed);
        $events = [];

        $window = max(1, $toMinute - $fromMinute);
        $possessions = max(1, (int) round(self::POSSESSIONS_PER_MATCH * $window / self::MATCH_MINUTES));

        for ($possession = 0; $possession < $possessions; $possession++) {
            $minute = $fromMinute + intdiv($possession * $window, $possessions);
            $starter = $this->startCarrier($players, $bands, $rng);
            $state = new MatchState($players[$starter]->zone, $starter, $minute);

            for ($tick = 0; $tick < self::MAX_TICKS_PER_POSSESSION; $tick++) {
                $options = $this->optionBuilder->build($state, $players);
                $actor = $players[$state->carrierId]->attributes;

                $choice = $this->decisionMaker->decide($options, $actor->vision, $rng);
                $outcome = $this->resolver->resolve($choice->option, $state->ballZone, $actor, $defense, $rng, $attackBias);

                $events[] = new MatchEvent(
                    $state->minute,
                    $choice->option->type,
                    $state->carrierId,
                    $choice->option->targetPlayerId,
                    $state->ballZone,
                    $choice->option->type === EventType::Shot ? null : $choice->option->resultZone,
                    $outcome->success,
                    $choice->decision,
                    $outcome->roll,
                );

                if ($outcome->possessionEnds) {
                    break;
                }

                if ($outcome->newBallZone !== null) {
                    $state->ballZone = $outcome->newBallZone;
                }

                if ($outcome->newCarrierId !== null) {
                    $state->carrierId = $outcome->newCarrierId;
                }
            }
        }

        return new MatchResult($events);
    }

    /**
     * Bucket the outfielders by pitch band so possessions can begin from
     * different areas. Ids are sorted within a band for stable, deterministic
     * indexing off the rng.
     *
     * @param  array<int, Player>  $players
     * @return array{deep: list<int>, mid: list<int>, adv: list<int>}
     */
    private function startBands(array $players): array
    {
        $bands = ['deep' => [], 'mid' => [], 'adv' => []];

        foreach ($players as $player) {
            $band = match (true) {
                $player->zone->x <= 1 => 'deep',
                $player->zone->x <= 3 => 'mid',
                default => 'adv',
            };
            $bands[$band][] = $player->id;
        }

        foreach ($bands as &$ids) {
            sort($ids);
        }

        return $bands;
    }

    /**
     * Pick where an attack starts: mostly a build-up from the back, often a
     * midfield regain, occasionally a win high up the pitch. Falls back through
     * the bands so a shape with an empty band still yields a carrier.
     *
     * @param  array<int, Player>  $players
     * @param  array{deep: list<int>, mid: list<int>, adv: list<int>}  $bands
     */
    private function startCarrier(array $players, array $bands, Rng $rng): int
    {
        $roll = $rng->next();
        $order = $roll < 0.6 ? ['deep', 'mid', 'adv']
            : ($roll < 0.95 ? ['mid', 'deep', 'adv'] : ['adv', 'mid', 'deep']);

        foreach ($order as $band) {
            if ($bands[$band] !== []) {
                return $bands[$band][$rng->below(count($bands[$band]))];
            }
        }

        return array_key_first($players) ?? 1;
    }
}
