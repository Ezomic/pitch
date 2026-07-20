<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Player;
use App\Sim\Domain\Zone;

final class OptionBuilder
{
    /**
     * Every legal option for the current carrier: a pass to each teammate,
     * a dribble one zone forward, and a shot when in range.
     *
     * @param  array<int, Player>  $players  keyed by player id
     * @return list<Option>
     */
    public function build(MatchState $state, array $players): array
    {
        $options = [];

        foreach ($players as $player) {
            if ($player->id === $state->carrierId) {
                continue;
            }

            $options[] = new Option(
                EventType::Pass,
                $player->zone,
                $player->id,
                $player->zone->threat(),
            );
        }

        if ($state->ballZone->x < Zone::MAX_X) {
            $forward = new Zone($state->ballZone->x + 1, $state->ballZone->y);
            $options[] = new Option(EventType::Dribble, $forward, null, $forward->threat());
        }

        if ($state->ballZone->inShootingRange()) {
            $options[] = new Option(EventType::Shot, $state->ballZone, null, $state->ballZone->threat());
        }

        return $options;
    }
}
