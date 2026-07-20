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
     * Same players + same seed + same defense always produce the identical event
     * log. With no defense the attack meets only zone-based pressure.
     *
     * @param  array<int, Player>  $players  keyed by player id
     */
    public function simulate(array $players, int $seed, ?Defense $defense = null): MatchResult
    {
        $defense ??= Defense::none();
        $rng = new Rng($seed);
        $events = [];

        for ($possession = 0; $possession < self::POSSESSIONS_PER_MATCH; $possession++) {
            $minute = intdiv($possession * self::MATCH_MINUTES, self::POSSESSIONS_PER_MATCH);
            $state = new MatchState(Roster::kickoffZone(), Roster::KICKOFF_CARRIER_ID, $minute);

            for ($tick = 0; $tick < self::MAX_TICKS_PER_POSSESSION; $tick++) {
                $options = $this->optionBuilder->build($state, $players);
                $actor = $players[$state->carrierId]->attributes;

                $choice = $this->decisionMaker->decide($options, $actor->vision, $rng);
                $outcome = $this->resolver->resolve($choice->option, $state->ballZone, $actor, $defense, $rng);

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
}
