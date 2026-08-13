<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\Career;
use App\Models\User;

/** Put the manager into one of their saves. */
class SwitchCareer
{
    public function handle(User $user, Career $career): void
    {
        if ($career->user_id !== $user->id) {
            return;
        }

        $career->forceFill(['last_played_at' => now()])->save();
        $user->forceFill(['current_career_id' => $career->id])->save();
    }
}
