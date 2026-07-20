<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use Illuminate\Support\Facades\DB;

class AssignSquadSlot
{
    private const int TEMP_SLOT = 99;

    /**
     * Put a player into a formation slot. If the player already holds another
     * slot, the two players swap slots; otherwise the player currently in the
     * target slot is replaced and leaves the squad.
     */
    public function handle(Squad $squad, int $slot, int $playerId): void
    {
        $assignments = $squad->assignments()->get();

        $target = $assignments->firstWhere('slot', $slot);
        $existing = $assignments->firstWhere('player_id', $playerId);

        if ($existing !== null && $existing->slot === $slot) {
            return;
        }

        DB::transaction(function () use ($squad, $slot, $playerId, $target, $existing): void {
            if ($target === null) {
                $squad->assignments()->create(['player_id' => $playerId, 'slot' => $slot]);

                return;
            }

            if ($existing === null) {
                $target->update(['player_id' => $playerId]);

                return;
            }

            $freedSlot = $existing->slot;
            $existing->update(['slot' => self::TEMP_SLOT]);
            $target->update(['slot' => $freedSlot]);
            $existing->update(['slot' => $slot]);
        });
    }
}
