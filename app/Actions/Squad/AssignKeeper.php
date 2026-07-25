<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use App\Sim\Domain\Position;
use Illuminate\Validation\ValidationException;

/**
 * Put a goalkeeper between the sticks. The keeper's shot-stopping is what turns
 * conceded chances into conceded goals, so this is the single lever over goals
 * against, separate from the outfield defence.
 */
class AssignKeeper
{
    /**
     * @throws ValidationException
     */
    public function handle(Squad $squad, Player $keeper): void
    {
        $selectable = Player::query()->selectableFor($squad->user_id)
            ->where('position', Position::Goalkeeper)
            ->whereKey($keeper->id)
            ->exists();

        if (! $selectable) {
            throw ValidationException::withMessages(['keeper' => 'That goalkeeper is not available.']);
        }

        $squad->forceFill(['goalkeeper_id' => $keeper->id])->save();
    }
}
