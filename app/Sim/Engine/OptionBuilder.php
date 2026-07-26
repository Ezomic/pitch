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

        // A cross from a wide, advanced position swings the ball into the box for
        // a striker to attack, an alternative to working it through the middle.
        if ($state->ballZone->x >= 4 && $state->ballZone->y !== Zone::CENTRE_Y) {
            $box = new Zone(Zone::MAX_X, Zone::CENTRE_Y);
            $target = $this->targetInBox($players, $state->carrierId);
            if ($target !== null) {
                $options[] = new Option(EventType::Cross, $box, $target, $box->threat());
            }
        }

        if ($state->ballZone->inShootingRange()) {
            $type = $state->headerNext ? EventType::Header : EventType::Shot;
            $options[] = new Option($type, $state->ballZone, null, $state->ballZone->threat());
        }

        return $options;
    }

    /**
     * The most central, advanced teammate to aim a cross at, if any.
     *
     * @param  array<int, Player>  $players
     */
    private function targetInBox(array $players, int $carrierId): ?int
    {
        $best = null;
        $bestScore = null;

        foreach ($players as $player) {
            if ($player->id === $carrierId) {
                continue;
            }

            $score = [$player->zone->x, -abs($player->zone->y - Zone::CENTRE_Y)];
            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = $player->id;
            }
        }

        return $best;
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
