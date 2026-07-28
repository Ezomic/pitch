<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Models\Player;

/**
 * Bring a fresh player on for one already on the pitch. The incoming player
 * takes the same slot and position; only their attributes and name change, so
 * the swap is a small edit of the persisted state and needs no re-simulation.
 */
class Substitute
{
    public function handle(LiveMatch $match, int $outSlot, Player $in): void
    {
        if ($match->subs_remaining <= 0) {
            return;
        }

        $state = $match->pitch_state;
        foreach ($state['players'] as &$player) {
            if ((int) $player['id'] === $outSlot && (int) $player['side'] === 0) {
                $player['attr'] = [$in->vision, $in->passing, $in->dribbling, $in->finishing, $in->tackling, $in->pace];
            }
        }
        unset($player);

        $players = $match->players;
        foreach ($players as &$meta) {
            if ((int) $meta['s'] === 0 && (int) $meta['slot'] === $outSlot) {
                $meta['name'] = $in->name;
            }
        }
        unset($meta);

        $match->update([
            'pitch_state' => $state,
            'players' => $players,
            'subs_remaining' => $match->subs_remaining - 1,
        ]);
    }
}
