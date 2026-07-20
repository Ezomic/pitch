<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Engine\Roster;
use Illuminate\Support\Facades\DB;

class EnsureSquad
{
    /**
     * Return the user's squad, creating and filling a default one on first use.
     * Slots are filled with pool players whose position matches the formation
     * slot where possible, falling back to any unused player.
     */
    public function handle(User $user): Squad
    {
        $existing = $user->squad()->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user): Squad {
            $squad = Squad::create([
                'user_id' => $user->id,
                'budget' => Squad::DEFAULT_BUDGET,
            ]);

            $pool = Player::all()->sortBy(fn (Player $p) => $p->value())->values();
            $used = [];

            foreach (Roster::formation() as $slot => [, $position]) {
                $player = $pool->first(fn (Player $p) => ! in_array($p->id, $used, true) && $p->position === $position)
                    ?? $pool->first(fn (Player $p) => ! in_array($p->id, $used, true));

                if ($player === null) {
                    continue;
                }

                $used[] = $player->id;
                $squad->assignments()->create([
                    'player_id' => $player->id,
                    'slot' => $slot,
                ]);
            }

            return $squad;
        });
    }
}
