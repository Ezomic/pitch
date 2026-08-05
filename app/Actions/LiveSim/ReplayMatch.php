<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Engine\Rng;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PitchState;
use App\Sim\Pitch\PositionalEngine;

/**
 * Re-simulate a match from its first tick so it can be watched again.
 *
 * This is the project's thesis made visible: the same seed and the same inputs
 * produce the same match, so a result can be re-watched rather than only read.
 * Nothing is written back; the stored match and its fixture are left alone.
 *
 * A seed on its own is not enough. Substitutions and mentality changes mutate
 * engine state while the match runs, so each was recorded with the tick it
 * happened on and is reapplied here at exactly that tick. Pausing at those
 * ticks costs nothing: resuming in slices is byte-identical to one continuous
 * run, which is what makes the live match resumable in the first place.
 *
 * Only the positions are regenerated. They are the expensive part and the part
 * that is never stored, whereas the commentary feed was written down as it
 * happened and is replayed from the record. That is deliberate: which events
 * become commentary is decided by their index within the slice they were
 * generated in (MatchCommentary gates on key % 2 and key % 5), and the client
 * chooses those slice boundaries, so the feed is not a pure function of the
 * seed the way the match itself is.
 */
class ReplayMatch
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly LivePitch $live = new LivePitch,
    ) {}

    /**
     * @return array{frames: list<array<string, mixed>>, homeGoals: int, awayGoals: int}
     */
    public function handle(LiveMatch $match): array
    {
        $state = PitchState::fromSnapshot($match->kickoff_state ?? $match->pitch_state);
        $rng = new Rng($match->seed);

        $frames = [];
        $homeGoals = 0;
        $awayGoals = 0;

        foreach ($this->segments($match) as [$from, $to, $interventions]) {
            foreach ($interventions as $intervention) {
                $state = $this->apply($state, $intervention);
            }

            if ($to <= $from) {
                continue;
            }

            $result = $this->engine->resume($state, $rng, $from, $to);
            $state = $result->state ?? $state;

            $frames = [...$frames, ...$this->live->frames($result->frames, $from)];
            $homeGoals = $result->homeGoals;
            $awayGoals = $result->awayGoals;
        }

        return [
            'frames' => $frames,
            'homeGoals' => $homeGoals,
            'awayGoals' => $awayGoals,
        ];
    }

    /**
     * The match split at every tick something changed, so each stretch runs
     * uninterrupted and each change lands on the tick it originally landed on.
     *
     * @return list<array{int, int, list<array<string, mixed>>}>
     */
    private function segments(LiveMatch $match): array
    {
        $byTick = [];
        foreach ($match->interventions ?? [] as $intervention) {
            $tick = max(0, min((int) $intervention['tick'], $match->total_ticks));
            $byTick[$tick][] = $intervention;
        }

        $boundaries = array_keys($byTick);
        sort($boundaries);

        $segments = [];
        $from = 0;
        foreach ($boundaries as $tick) {
            if ($tick > $from) {
                $segments[] = [$from, $tick, []];
                $from = $tick;
            }

            $segments[] = [$from, $from, $byTick[$tick]];
        }

        $segments[] = [$from, $match->current_tick, []];

        return $segments;
    }

    /**
     * A player's attributes are readonly on PlayerState, so a substitution is
     * applied to the snapshot and the state rebuilt from it. That is exactly
     * what the live Substitute action does to the stored state, which is why
     * the replay reproduces it rather than merely approximating it.
     *
     * @param  array<string, mixed>  $intervention
     */
    private function apply(PitchState $state, array $intervention): PitchState
    {
        if ($intervention['type'] === 'mentality') {
            $state->homeMentality = (string) $intervention['value'];

            return $state;
        }

        $slot = (int) $intervention['slot'];

        $snapshot = $state->toSnapshot();
        foreach ($snapshot['players'] as &$player) {
            if ((int) $player['id'] === $slot && (int) $player['side'] === 0) {
                $player['attr'] = $intervention['attr'];
            }
        }
        unset($player);

        return PitchState::fromSnapshot($snapshot);
    }
}
