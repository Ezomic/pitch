<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Decision;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Roll;

/**
 * Turning what happened on the pitch into the MatchEvent shape the rest of the
 * app reads: standings, the commentary feed, the decision inspector and the
 * persisted event log all consume this and nothing else.
 *
 * Continuous positions are mapped onto the coarse zone grid here, always from
 * the acting side's point of view, so an event reads the same whichever end of
 * the pitch the actor happens to be attacking.
 */
final class Events
{
    public static function of(
        int $minute,
        EventType $type,
        PlayerState $actor,
        ?int $targetId,
        Vec2 $from,
        ?Vec2 $to,
        bool $success,
        ?Decision $decision = null,
        ?Roll $roll = null,
    ): MatchEvent {
        return new MatchEvent(
            $minute,
            $type,
            $actor->id,
            $targetId,
            Geometry::zone($from, $actor->side),
            $to !== null ? Geometry::zone($to, $actor->side) : null,
            $success,
            $decision,
            $roll,
        );
    }
}
