<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\EventType;
use App\Sim\Domain\Player;
use App\Sim\Domain\Zone;

final class OptionBuilder
{
    /**
     * How strongly a pass's appeal decays per grid step of distance beyond the
     * first. High enough that a nearby progressive team-mate usually beats the
     * hopeful ball over the top, so the ball climbs the pitch band by band
     * through several players instead of one long punt to the striker.
     */
    private const float DISTANCE_PENALTY = 0.25;

    /** Reward for advancing the ball, capped so a two-band pass is plenty. */
    private const float PROGRESS_BONUS = 0.10;

    /**
     * Every legal option for the current carrier: a pass to each teammate, a
     * dribble one zone forward, and a shot when in range. Passes and dribbles are
     * valued by how far they progress the ball, discounted by distance, so the
     * decision favours reachable, progressive options over the hospital ball.
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
                $this->progressiveThreat($state->ballZone, $player->zone),
            );
        }

        if ($state->ballZone->x < Zone::MAX_X) {
            $forward = new Zone($state->ballZone->x + 1, $state->ballZone->y);
            $options[] = new Option(
                EventType::Dribble,
                $forward,
                null,
                $this->progressiveThreat($state->ballZone, $forward),
            );
        }

        if ($state->ballZone->inShootingRange()) {
            $options[] = new Option(EventType::Shot, $state->ballZone, null, $state->ballZone->threat());
        }

        return $options;
    }

    /**
     * A moving-the-ball option's appeal: the destination's positional threat,
     * bonused for advancing the ball and penalised for the distance travelled.
     */
    private function progressiveThreat(Zone $ball, Zone $target): float
    {
        $progress = max(0, $target->x - $ball->x);
        $distance = abs($target->x - $ball->x) + abs($target->y - $ball->y);

        return $target->threat()
            + self::PROGRESS_BONUS * min($progress, 2)
            - self::DISTANCE_PENALTY * max(0, $distance - 1);
    }
}
