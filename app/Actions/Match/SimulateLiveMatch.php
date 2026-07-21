<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Sim\Domain\EventType;
use App\Sim\Engine\MatchEngine;
use App\Sim\Squad\MatchNarrator;
use App\Sim\Squad\TeamSetup;

/**
 * Simulate a window of a live match from the user's point of view and return a
 * single minute-ordered feed of both sides' key moments, plus the window's
 * goals. The window seed keys off the start minute, so re-simulating a tail
 * after a substitution is deterministic and only re-rolls that tail.
 */
class SimulateLiveMatch
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly MatchNarrator $narrator = new MatchNarrator,
    ) {}

    /**
     * @param  array<int, string>  $names  slot id => player name
     * @return array{moments: list<array<string, mixed>>, scored: int, conceded: int, scorers: list<array{minute: int, slot: int}>}
     */
    public function handle(
        TeamSetup $user,
        array $names,
        TeamSetup $opponent,
        string $opponentName,
        int $seed,
        int $fromMinute = 0,
        int $toMinute = 90,
    ): array {
        $windowSeed = $seed + $fromMinute;

        $attack = $this->engine->simulate(
            $user->attackers(), $windowSeed, $opponent->defence(), $user->formation, $user->attackBias(), $fromMinute, $toMinute,
        );

        $defence = $this->engine->simulate(
            $opponent->attackers(), $windowSeed, $user->defence(), $opponent->formation, $opponent->attackBias(), $fromMinute, $toMinute,
        );

        $moments = [];
        $scorers = [];

        foreach ($this->narrator->feed($attack, $names) as $moment) {
            $moments[] = ['minute' => $moment->minute, 'side' => 'home', 'kind' => $moment->kind, 'text' => $moment->text];
        }

        foreach ($attack->events as $event) {
            if ($event->type === EventType::Shot && $event->success) {
                $scorers[] = ['minute' => $event->minute, 'slot' => $event->actorId];
            }
        }

        foreach ($defence->events as $event) {
            if ($event->type === EventType::Shot && $event->success) {
                $moments[] = ['minute' => $event->minute, 'side' => 'away', 'kind' => 'goal', 'text' => "{$opponentName} score."];
            }
        }

        usort($moments, fn (array $a, array $b): int => $a['minute'] <=> $b['minute']);

        return ['moments' => $moments, 'scored' => $attack->goals, 'conceded' => $defence->goals, 'scorers' => $scorers];
    }
}
