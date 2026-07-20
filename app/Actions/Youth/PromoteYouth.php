<?php

declare(strict_types=1);

namespace App\Actions\Youth;

use App\Models\Player;
use Illuminate\Validation\ValidationException;

class PromoteYouth
{
    /**
     * Step a ready prospect up into the first-team pool. It keeps its owner, so
     * it becomes selectable for the squad while other clubs never see it.
     *
     * @throws ValidationException
     */
    public function handle(Player $player): void
    {
        if (! $player->isPromotable()) {
            throw ValidationException::withMessages(['player' => 'That prospect is not ready to be promoted.']);
        }

        $player->forceFill(['is_youth' => false])->save();
    }
}
