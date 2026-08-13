<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\Career;
use App\Models\User;

/**
 * Delete a save and everything in it.
 *
 * The career_id foreign keys cascade, so this takes the squad, the seasons,
 * the players, the scouts, the news and every stored match with it. That is
 * what deleting a save means, and it is why the last one cannot go: a manager
 * must always have somewhere to play.
 */
class DeleteCareer
{
    public function handle(User $user, Career $career): bool
    {
        if ($career->user_id !== $user->id || $user->careers()->count() < 2) {
            return false;
        }

        $wasActive = $user->current_career_id === $career->id;
        $career->delete();

        if ($wasActive) {
            $next = $user->careers()->orderByDesc('last_played_at')->orderByDesc('id')->first();
            $user->forceFill(['current_career_id' => $next?->id])->save();
        }

        return true;
    }
}
