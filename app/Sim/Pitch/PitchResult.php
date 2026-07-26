<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\MatchEvent;

/**
 * The output of a positional match: the same curated MatchEvent stream the rest
 * of the app already understands (so standings, the feed and stats keep working),
 * plus a per-tick position stream (all 22 players and the ball) that the replay
 * consumes directly instead of inventing positions from the ball path.
 *
 * @phpstan-type Frame array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>}
 */
final readonly class PitchResult
{
    /**
     * @param  list<MatchEvent>  $events
     * @param  list<Frame>  $frames
     */
    public function __construct(
        public array $events,
        public array $frames,
        public int $homeGoals,
        public int $awayGoals,
    ) {}
}
