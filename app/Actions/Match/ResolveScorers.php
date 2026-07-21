<?php

declare(strict_types=1);

namespace App\Actions\Match;

/**
 * Resolve the slots that scored in a window to the player ids in that lineup, so
 * a goal is credited to whoever was on the pitch when it went in (not to a player
 * who came on later). Slots with no real player behind them are dropped.
 */
class ResolveScorers
{
    /**
     * @param  list<array{minute: int, slot: int}>  $scorerSlots
     * @param  array<int, int>  $lineup  slot id => player id
     * @return list<array{minute: int, player_id: int}>
     */
    public static function forLineup(array $scorerSlots, array $lineup): array
    {
        $resolved = [];

        foreach ($scorerSlots as $scorer) {
            $playerId = $lineup[$scorer['slot']] ?? null;
            if ($playerId !== null) {
                $resolved[] = ['minute' => $scorer['minute'], 'player_id' => (int) $playerId];
            }
        }

        return $resolved;
    }
}
