<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\Rng;

/**
 * Everything that happens when play stops: throw-ins, corners, goal kicks, and
 * the fouls that turn into free kicks and penalties.
 *
 * The ball is placed by hand at a restart rather than travelling there, so this
 * is also where the pause that lets the shape reset is applied. Draws are taken
 * from the Rng the engine hands in, in the order they always were, which is why
 * lifting this out leaves every seeded match exactly as it was.
 */
final class Restarts
{
    private const int DEADBALL_TICKS = 7;         // pause while a set piece is taken

    private const float FREE_KICK_RANGE = 0.32;   // a foul this close to goal is a direct free kick

    private const float PENALTY_CONVERSION = 0.78;

    /**
     * Award a set piece: emit its event, place the ball at the restart spot with a
     * taker of the awarded side on it, and hold play for a beat so it reads as a
     * dead ball. A goal kick is taken by the keeper, everything else by the nearest
     * team-mate to the spot.
     *
     * @param  list<MatchEvent>  $events
     */
    public function awardRestart(PitchState $state, array &$events, int $minute, EventType $type, int $side, Vec2 $spot, ?PlayerState $creditActor = null): void
    {
        $taker = $type === EventType::GoalKick
            ? ($state->players[PlayerState::id($side, 0)] ?? null)
            : $this->nearestTeammateTo($state, $side, $spot);

        if ($taker === null) {
            return;
        }

        $events[] = Events::of($minute, $type, $creditActor ?? $taker, null, $spot, null, true);

        $taker->pos = $spot;
        $state->carrierId = $taker->id;
        $state->possessing = $side;
        $state->ball = $spot;
        $state->ballTarget = null;
        $state->ballTo = PitchState::NO_CARRIER;
        $state->ballKind = 'idle';
        $state->ballGoal = false;
        $state->holdTicks = 0;
        $state->deadBall = self::DEADBALL_TICKS;
        $state->teleported = true; // the ball is placed at the restart spot
    }

    private function nearestTeammateTo(PitchState $state, int $side, Vec2 $spot): ?PlayerState
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side !== $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = $player->pos->distanceTo($spot);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return $best;
    }

    public function cornerSpot(int $attackingSide, float $y): Vec2
    {
        return new Vec2($attackingSide === 0 ? 0.99 : 0.01, $y < 0.5 ? 0.02 : 0.98);
    }

    public function goalKickSpot(int $defendingSide): Vec2
    {
        return new Vec2($defendingSide === 0 ? 0.08 : 0.92, 0.5);
    }

    /**
     * Resolve a foul by where it happened: a penalty inside the box, a direct free
     * kick within range of goal, or a possession free kick anywhere else.
     *
     * @param  list<MatchEvent>  $events
     */
    public function foul(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, PlayerState $defender, bool $slide = false): void
    {
        $goal = Geometry::goalOf($carrier->side);

        if (Geometry::inPenaltyBox($carrier->pos, $carrier->side)) {
            $this->penalty($state, $events, $rng, $minute, $carrier);

            return;
        }

        if ($carrier->pos->distanceTo($goal) < self::FREE_KICK_RANGE) {
            $events[] = Events::of($minute, EventType::Foul, $defender, null, $carrier->pos, null, true);
            $this->freeKickShot($state, $events, $rng, $minute, $carrier, $goal);

            return;
        }

        $this->awardRestart($state, $events, $minute, EventType::Foul, $carrier->side, $carrier->pos, $defender);
    }

    /**
     * A penalty: taken from the spot, converted at a high fixed rate, saved by the
     * keeper otherwise.
     *
     * @param  list<MatchEvent>  $events
     */
    private function penalty(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $winner): void
    {
        $side = $winner->side;
        $spot = Geometry::penaltySpot($side);
        $taker = $this->nearestTeammateTo($state, $side, $spot) ?? $winner;
        $goal = Geometry::goalOf($side);
        $keeper = $state->players[PlayerState::id(1 - $side, 0)] ?? null;

        $events[] = Events::of($minute, EventType::Penalty, $taker, null, $spot, null, true);

        $scored = $rng->next() < self::PENALTY_CONVERSION;
        $events[] = Events::of($minute, EventType::Shot, $taker, null, $spot, null, $scored);

        $taker->pos = $spot;
        $state->carrierId = PitchState::NO_CARRIER;
        $state->possessing = $side;
        $state->ball = $spot;
        $state->ballKind = 'shot';
        $state->ballSpeed = Ball::SHOT_SPEED;
        $state->ballGoal = $scored;

        if ($scored || $keeper === null) {
            $state->ballTarget = $goal;
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        $events[] = Events::of($minute, EventType::Save, $keeper, null, $goal, null, true);
        $state->ballTarget = $keeper->pos;
        $state->ballTo = $keeper->id;
    }

    /**
     * A direct free kick at goal: hard to score past a set keeper and a wall, and
     * a miss becomes a save or a goal kick.
     *
     * @param  list<MatchEvent>  $events
     */
    private function freeKickShot(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $winner, Vec2 $goal): void
    {
        $side = $winner->side;
        $spot = $winner->pos;
        $keeper = $state->players[PlayerState::id(1 - $side, 0)] ?? null;

        $scored = $rng->next() < max(0.03, $winner->attributes->finishing / 100 * 0.12);
        $events[] = Events::of($minute, EventType::Shot, $winner, null, $spot, null, $scored);

        $state->carrierId = PitchState::NO_CARRIER;
        $state->possessing = $side;
        $state->ball = $spot;
        $state->ballKind = 'shot';
        $state->ballSpeed = Ball::SHOT_SPEED;
        $state->ballGoal = $scored;

        if ($scored) {
            $state->ballTarget = $goal;
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        if ($rng->next() < 0.5 && $keeper !== null) {
            $events[] = Events::of($minute, EventType::Save, $keeper, null, $goal, null, true);
            $state->ballTarget = $keeper->pos;
            $state->ballTo = $keeper->id;

            return;
        }

        $state->pendingType = EventType::GoalKick;
        $state->pendingSide = 1 - $side;
        $state->pendingSpot = $this->goalKickSpot(1 - $side);
        $state->ballTarget = $goal;
        $state->ballTo = PitchState::NO_CARRIER;
    }
}
