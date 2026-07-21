<?php

declare(strict_types=1);

namespace App\Actions\Youth;

use App\Models\Player;
use App\Models\User;
use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\TeamSetup;
use Illuminate\Support\Collection;

/**
 * Assemble the user's academy into a fieldable youth XI: the strongest prospects
 * fill the formation, and any empty slots are covered by raw trialists, so a thin
 * academy fields a weak side.
 */
class BuildYouthTeam
{
    private const int TRIALIST = 6;

    public function forUser(User $user): TeamSetup
    {
        $prospects = $this->prospects($user)->values();
        $formation = Formation::balanced();

        $bySlot = [];
        foreach ($formation->slots() as $index => $slot) {
            $player = $prospects->get($index);
            $bySlot[$slot] = $player instanceof Player
                ? $player->matchAttributes()
                : new Attributes(self::TRIALIST, self::TRIALIST, self::TRIALIST, self::TRIALIST, self::TRIALIST, self::TRIALIST);
        }

        return new TeamSetup($bySlot, $formation, Mentality::Balanced);
    }

    /**
     * The prospects who feature, strongest first (at most a full eleven).
     *
     * @return Collection<int, Player>
     */
    public function featured(User $user): Collection
    {
        return $this->prospects($user)->take(count(Formation::balanced()->slots()));
    }

    /**
     * @return Collection<int, Player>
     */
    private function prospects(User $user): Collection
    {
        return Player::query()
            ->where('user_id', $user->id)
            ->where('is_youth', true)
            ->get()
            ->sortByDesc(fn (Player $p) => $p->overall());
    }
}
