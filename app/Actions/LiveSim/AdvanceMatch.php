<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Engine\Rng;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;

/**
 * Advance a live match by one slice: restore the exact engine state, simulate
 * the next window of ticks, persist the new state, and return that slice's
 * position frames and key moments for the 2D replay to play.
 */
class AdvanceMatch
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly LivePitch $live = new LivePitch,
        private readonly FinishFixture $finishFixture = new FinishFixture,
    ) {}

    /**
     * @return array{frames: list<array<string, mixed>>, moments: list<array<string, mixed>>, goals: list<array{minute: int, side: int}>, homeGoals: int, awayGoals: int, tick: int, finished: bool}
     */
    public function handle(LiveMatch $match, int $chunkTicks = 30): array
    {
        if ($match->status === LiveMatch::FINISHED || $match->current_tick >= $match->total_ticks) {
            return $this->done($match);
        }

        $state = PitchState::fromSnapshot($match->pitch_state);
        $rng = Rng::fromState($match->rng_state);

        $from = $match->current_tick;
        $to = min($from + $chunkTicks, $match->total_ticks);
        $result = $this->engine->resume($state, $rng, $from, $to);

        $names = $this->names($match);
        $frames = $this->live->frames($result->frames, $from);
        $moments = $this->live->moments($result->events, $names);
        $goals = [];
        $scorers = [];
        foreach ($result->events as $event) {
            if (! $event->type->isShot() || ! $event->success) {
                continue;
            }

            $side = $event->actorId >= 100 ? 1 : 0;
            $goals[] = ['minute' => $event->minute, 'side' => $side];

            // Only the manager's own goals need crediting; the slot is recovered
            // from the actor id, which encodes side and slot together.
            if ($side === 0) {
                $scorers[] = ['minute' => $event->minute, 'slot' => $event->actorId % 100];
            }
        }
        $finished = $to >= $match->total_ticks;

        $match->update([
            'current_tick' => $to,
            'pitch_state' => $result->state?->toSnapshot() ?? $match->pitch_state,
            'rng_state' => $rng->stateValue(),
            'home_goals' => $result->homeGoals,
            'away_goals' => $result->awayGoals,
            'moments' => array_merge($match->moments, $moments),
            'scorers' => [...$match->scorers ?? [], ...$scorers],
            'status' => $finished ? LiveMatch::FINISHED : LiveMatch::LIVE,
        ]);

        // Full time on a league match settles the fixture: the score counts, the
        // goals are credited and the eleven's condition is worked out.
        if ($finished) {
            $this->finishFixture->handle($match->fresh() ?? $match);
        }

        return [
            'frames' => $frames,
            'moments' => $moments,
            'goals' => $goals,
            'homeGoals' => $result->homeGoals,
            'awayGoals' => $result->awayGoals,
            'tick' => $to,
            'finished' => $finished,
        ];
    }

    /**
     * @return array{frames: list<array<string, mixed>>, moments: list<array<string, mixed>>, goals: list<array{minute: int, side: int}>, homeGoals: int, awayGoals: int, tick: int, finished: bool}
     */
    private function done(LiveMatch $match): array
    {
        return [
            'frames' => [],
            'moments' => [],
            'goals' => [],
            'homeGoals' => $match->home_goals,
            'awayGoals' => $match->away_goals,
            'tick' => $match->current_tick,
            'finished' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function names(LiveMatch $match): array
    {
        $names = [];
        foreach ($match->players as $player) {
            $id = (int) $player['s'] * 100 + (int) $player['slot'];
            $names[$id] = $player['s'] === 0 ? ($player['name'] ?? "Slot {$player['slot']}") : 'Opposition';
        }

        return $names;
    }
}
