<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use App\Sim\Domain\Position;
use Illuminate\Validation\ValidationException;

/**
 * Nominate the club's set-piece taker: the outfielder whose delivery and finish
 * turn corners and free-kicks into goals. A clear lever over set-piece output,
 * separate from open play.
 */
class AssignSetPieceTaker
{
    /**
     * @throws ValidationException
     */
    public function handle(Squad $squad, Player $taker): void
    {
        $selectable = Player::query()->selectableFor($squad->user_id)
            ->where('position', '!=', Position::Goalkeeper)
            ->whereKey($taker->id)
            ->exists();

        if (! $selectable) {
            throw ValidationException::withMessages(['setPiece' => 'That player cannot take set pieces.']);
        }

        $squad->forceFill(['set_piece_taker_id' => $taker->id])->save();
    }
}
