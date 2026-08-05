<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Engine\Mentality;

/**
 * Change the user side's mentality mid-match. It is stored on the engine state,
 * so the very next advance plays with the new instruction.
 */
class SetMentality
{
    public function handle(LiveMatch $match, Mentality $mentality): void
    {
        $state = $match->pitch_state;
        $state['homeMentality'] = $mentality->value;
        $match->update(['pitch_state' => $state]);
    }
}
