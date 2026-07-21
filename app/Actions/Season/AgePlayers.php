<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\User;

/**
 * A year passes between campaigns: every player the user owns ages by one, and
 * seniors past their peak start to regress, shedding a little from every
 * attribute so an ageing squad visibly needs refreshing.
 */
class AgePlayers
{
    private const int PEAK_AGE = 30;

    public function handle(User $user): void
    {
        foreach (Player::query()->where('user_id', $user->id)->get() as $player) {
            $age = $player->age + 1;
            $update = ['age' => $age];

            if (! $player->is_youth && $age > self::PEAK_AGE) {
                $decline = $age >= 34 ? 2 : 1;

                foreach (Player::ATTRIBUTES as $attribute) {
                    $update[$attribute] = max(1, $player->{$attribute} - $decline);
                }
            }

            $player->forceFill($update)->save();
        }
    }
}
