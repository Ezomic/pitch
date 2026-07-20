<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignSquadSlot
{
    private const int TEMP_SLOT = 99;

    /**
     * Put a player into a formation slot. If the player already holds another
     * slot, the two players swap slots (cost-neutral); otherwise the player in
     * the target slot is replaced and leaves the squad, subject to the budget.
     *
     * @throws ValidationException when a replacement would exceed the budget
     */
    public function handle(Squad $squad, int $slot, int $playerId): void
    {
        $assignments = $squad->assignments()->with('player')->get();

        $target = $assignments->firstWhere('slot', $slot);
        $existing = $assignments->firstWhere('player_id', $playerId);

        if ($existing !== null && $existing->slot === $slot) {
            return;
        }

        if ($target !== null && $existing === null) {
            $incoming = Player::findOrFail($playerId);
            $spent = $assignments->sum(fn ($assignment) => $assignment->player->value());
            $newTotal = $spent - $target->player->value() + $incoming->value();

            if ($newTotal > $squad->budget) {
                throw ValidationException::withMessages([
                    'player_id' => 'That player would put you over budget.',
                ]);
            }
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
