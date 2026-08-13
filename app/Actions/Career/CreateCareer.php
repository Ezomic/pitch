<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\Career;
use App\Models\User;

/**
 * Start a new save and switch to it. The world it will be played in is built
 * lazily by the ensure actions on the first request that needs one, so nothing
 * is generated here beyond the career itself.
 */
class CreateCareer
{
    public function handle(User $user, string $name): Career
    {
        $career = $user->careers()->create([
            'name' => $name,
            'type' => Career::SOLO,
            'status' => 'active',
            'last_played_at' => now(),
        ]);

        $user->forceFill(['current_career_id' => $career->id])->save();

        return $career;
    }
}
