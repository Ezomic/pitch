<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Models\MatchSession;
use App\Models\Player;
use App\Models\Squad;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

/**
 * Make a substitution mid-match: the moments already played (before the current
 * minute) are locked, and the rest of the match is re-simulated with the new
 * lineup. A given minute + lineup is deterministic, so only the tail re-rolls.
 */
class SubstituteLive
{
    public function __construct(
        private readonly SimulateLiveMatch $simulate = new SimulateLiveMatch,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(MatchSession $session, Squad $squad, Team $opponent, int $minute, int $slot, int $incoming): void
    {
        $lineup = array_map('intval', $session->lineup ?? []);
        $bench = array_map('intval', $session->bench ?? []);

        if ($session->subs_remaining < 1) {
            throw ValidationException::withMessages(['sub' => 'You have used all your substitutions.']);
        }
        if (! in_array($incoming, $bench, true)) {
            throw ValidationException::withMessages(['sub' => 'That player is not on the bench.']);
        }
        if (in_array($incoming, $lineup, true)) {
            throw ValidationException::withMessages(['sub' => 'That player is already on the pitch.']);
        }
        if (! array_key_exists($slot, $lineup)) {
            throw ValidationException::withMessages(['sub' => 'There is no player in that position.']);
        }
        $minute = max(0, min(90, $minute));

        $lineup[$slot] = $incoming;
        $bench = array_values(array_diff($bench, [$incoming]));

        $names = [];
        $players = Player::query()->whereIn('id', array_values($lineup))->get()->keyBy('id');
        foreach ($lineup as $lineupSlot => $playerId) {
            $names[$lineupSlot] = $players->get($playerId)->name;
        }

        $locked = array_values(array_filter(
            $session->moments,
            fn (array $moment) => $moment['minute'] < $minute,
        ));

        $tail = $this->simulate->handle(
            $squad->setupFrom($lineup), $names, $opponent->setup(), $opponent->name, $session->seed, $minute, 90,
        );

        $moments = array_merge($locked, $tail['moments']);

        $session->update([
            'lineup' => $lineup,
            'bench' => $bench,
            'subs_remaining' => $session->subs_remaining - 1,
            'moments' => $moments,
            'home_goals' => $this->countGoals($moments, 'home'),
            'away_goals' => $this->countGoals($moments, 'away'),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $moments
     */
    private function countGoals(array $moments, string $side): int
    {
        return count(array_filter(
            $moments,
            fn (array $moment) => $moment['side'] === $side && $moment['kind'] === 'goal',
        ));
    }
}
