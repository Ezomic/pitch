<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;

/**
 * Change the user side's mentality mid-match. It is stored on the engine state,
 * so the very next advance plays with the new instruction.
 */
class SetMentality
{
    private const array ALLOWED = ['attacking', 'balanced', 'defensive'];

    public function handle(LiveMatch $match, string $mentality): void
    {
        if (! in_array($mentality, self::ALLOWED, true)) {
            return;
        }

        $state = $match->pitch_state;
        $state['homeMentality'] = $mentality;
        $match->update(['pitch_state' => $state]);
    }
}
