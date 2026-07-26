<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Player;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Rng;

/**
 * A positional match engine: 22 players with real, continuous positions that
 * drive the ball's decisions, simulated over a fixed-timestep tick loop. Both
 * teams are on the pitch at once and possession changes hands where the ball
 * actually is, so build-up, passing and defending emerge from position rather
 * than being invented afterwards from the ball path.
 *
 * Same players + seed + formations always produce the identical match: every
 * random draw comes from the Rng in a fixed per-tick order, all mutable match
 * state lives on PitchState (nothing persists on the engine between simulate
 * calls), and positions are deterministic IEEE-754 float maths.
 *
 * Stage 1 (PITCH-87) establishes the loop, movement and possession core with
 * crude defending; later stages add real defensive shape, decision quality and
 * tactics.
 */
final class PositionalEngine
{
    private const float TICK = 0.2;               // seconds of play per tick

    private const int TOTAL_TICKS = 2700;         // a match's worth of action

    private const int DECIDE_TICKS = 5;           // the carrier acts about once a second

    private const float PASS_SPEED = 0.34;        // ball speed in flight, per second

    private const float SHOT_SPEED = 0.55;

    private const float SHOOT_RANGE = 0.26;       // distance to goal a shot is viable from

    private const float MAX_PASS = 0.55;          // longest pass a player attempts

    private const float PRESS_RADIUS = 0.07;      // a defender this close forces a decision

    private const float TACKLE_RADIUS = 0.028;    // a defender this close contests the ball

    private const float MARK_RADIUS = 0.05;       // an opponent this close counts as marking

    private const float MARK = 0.15;              // how hard a defender shades onto its man

    private const float LANE_RADIUS = 0.04;       // a defender this near a pass lane can cut it

    private const float LANE_WEIGHT = 0.2;        // how much a covered lane suppresses a pass

    /**
     * @param  array<int, Player>  $home  slot id => player (ten outfielders)
     * @param  array<int, Player>  $away  slot id => player (ten outfielders)
     */
    public function simulate(array $home, array $away, int $seed, ?Formation $homeFormation = null, ?Formation $awayFormation = null): PitchResult
    {
        $rng = new Rng($seed);
        $state = $this->kickOff($this->buildStates($home, $away), side: 0);

        /** @var list<MatchEvent> $events */
        $events = [];
        /** @var list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>}> $frames */
        $frames = [];
        $homeGoals = 0;
        $awayGoals = 0;

        for ($tick = 0; $tick < self::TOTAL_TICKS; $tick++) {
            $minute = (int) min(89, $tick / self::TOTAL_TICKS * 90);

            $this->setTargets($state);
            $this->moveAll($state);

            if ($state->inFlight()) {
                $scorer = $this->advanceBall($state, $events, $minute);

                if ($scorer >= 0) {
                    $scorer === 0 ? $homeGoals++ : $awayGoals++;
                    $state = $this->kickOff($state->players, side: 1 - $scorer);
                }
            } else {
                $carrier = $state->carrier();

                if ($carrier !== null) {
                    $state->ball = $carrier->pos;
                    $state->holdTicks++;

                    if (! $this->contest($state, $events, $rng, $minute)
                        && ($state->holdTicks >= self::DECIDE_TICKS || $this->pressed($state))) {
                        $this->decide($state, $events, $rng, $minute);
                        $state->holdTicks = 0;
                    }
                }
            }

            $frames[] = $this->snapshot($state, $minute);
        }

        return new PitchResult($events, $frames, $homeGoals, $awayGoals);
    }

    /**
     * @param  array<int, Player>  $home
     * @param  array<int, Player>  $away
     * @return array<int, PlayerState>
     */
    private function buildStates(array $home, array $away): array
    {
        $states = [];

        foreach ([[0, $home], [1, $away]] as [$side, $players]) {
            /** @var array<int, Player> $players */
            foreach ($players as $slot => $player) {
                $anchor = $this->anchor($side, $player->zone->x / Zone::MAX_X, $player->zone->y / Zone::MAX_Y);
                $id = PlayerState::id($side, $slot);
                $states[$id] = new PlayerState($id, $side, $slot, $player->position, $anchor, $player->attributes);
            }

            // Every team needs a keeper on its own line; a solid default until the
            // goalkeeper becomes a positional lever in a later stage.
            $keeperX = $side === 0 ? 0.03 : 0.97;
            $keeperId = PlayerState::id($side, 0);
            $states[$keeperId] = new PlayerState(
                $keeperId, $side, 0, Position::Goalkeeper,
                new Vec2($keeperX, 0.5), new Attributes(45, 45, 45, 45, 62, 45),
            );
        }

        return $states;
    }

    /** Place a side-relative anchor (advanced = toward the opponent goal) in pitch space. */
    private function anchor(int $side, float $advance, float $width): Vec2
    {
        // Home attacks toward x=1, so a more advanced zone sits further right; the
        // away side is mirrored. Both teams start compressed into their own half.
        $x = $side === 0 ? 0.06 + $advance * 0.5 : 0.94 - $advance * 0.5;

        return new Vec2($x, 0.08 + $width * 0.84);
    }

    /**
     * @param  array<int, PlayerState>  $states
     */
    private function kickOff(array $states, int $side): PitchState
    {
        $state = new PitchState($states, new Vec2(0.5, 0.5), PitchState::NO_CARRIER);
        $state->possessing = $side;

        // A central player of the restarting side taps off from the centre spot.
        $starter = $this->centralOutfielder($state, $side);
        if ($starter !== null) {
            $starter->pos = new Vec2(0.5, 0.5);
            $state->carrierId = $starter->id;
            $state->ball = $starter->pos;
        }

        return $state;
    }

    private function setTargets(PitchState $state): void
    {
        $ballX = $state->ball->x;

        foreach ($state->players as $player) {
            if ($player->isGoalkeeper()) {
                $goalX = $player->side === 0 ? 0.03 : 0.97;
                $player->target = new Vec2($goalX, 0.5 + ($state->ball->y - 0.5) * 0.4);

                continue;
            }

            if ($player->id === $state->carrierId) {
                // Drive at goal, but veer wide of a closing defender rather than
                // running straight into the tackle.
                $goal = $this->goalOf($player->side);
                $defender = $this->nearestOpponent($state, $player->pos, $player->side);
                if ($defender !== null && $player->pos->distanceTo($defender->pos) < 0.11) {
                    $away = $player->pos->y >= $defender->pos->y ? 1.0 : -1.0;
                    $player->target = (new Vec2($goal->x, $player->pos->y + $away * 0.18))->clampToPitch();
                } else {
                    $player->target = $goal;
                }

                continue;
            }

            if ($state->inFlight() && $player->id === $state->ballTo && $state->ballTarget !== null) {
                // A receiver runs onto the ball.
                $player->target = $state->ballTarget;

                continue;
            }

            if ($player->side === $state->possessing) {
                // Slide the attacking block up-field as the ball advances so there
                // are options ahead and forwards reach the box.
                $ballAdvance = $player->side === 0 ? $ballX : 1.0 - $ballX;
                $lift = max(0.0, ($ballAdvance - 0.25) * 0.55);
                $dir = $player->side === 0 ? 1.0 : -1.0;
                $player->target = (new Vec2(
                    $player->anchor->x + $dir * $lift,
                    $player->anchor->y + ($state->ball->y - 0.5) * 0.2,
                ))->clampToPitch();

                continue;
            }

            // Out of possession: the nearest man presses the carrier; the rest
            // hold a compact block goal-side of the ball, each shading onto the
            // runner it is responsible for so the pass into him is screened.
            $carrier = $state->carrier();
            if ($carrier !== null && $this->isNearestDefender($state, $player, $carrier->pos)) {
                $player->target = $carrier->pos;

                continue;
            }

            $ownGoalX = $player->side === 0 ? 0.0 : 1.0;
            $blockX = $ballX + ($ownGoalX - $ballX) * 0.45;
            $base = new Vec2(
                $player->anchor->x + ($blockX - $player->anchor->x) * 0.5,
                $player->anchor->y + ($state->ball->y - 0.5) * 0.3,
            );

            $man = $this->markAssignment($state, $player);
            if ($man !== null) {
                $dir = $player->side === 0 ? -1.0 : 1.0; // toward own goal
                $goalSide = new Vec2($man->pos->x + $dir * 0.03, $man->pos->y);
                $base = new Vec2(
                    $base->x + ($goalSide->x - $base->x) * self::MARK,
                    $base->y + ($goalSide->y - $base->y) * self::MARK,
                );
            }

            $player->target = $base->clampToPitch();
        }
    }

    private function moveAll(PitchState $state): void
    {
        foreach ($state->players as $player) {
            $player->pos = $player->pos->moveToward($player->target, $player->speed() * self::TICK)->clampToPitch();
        }
    }

    /**
     * Advance a ball in flight. Returns the side (0 or 1) that just scored when a
     * struck shot reaches the net, otherwise -1.
     *
     * @param  list<MatchEvent>  $events
     */
    private function advanceBall(PitchState $state, array &$events, int $minute): int
    {
        if ($state->ballTarget === null) {
            $state->carrierId = $state->ballTo;

            return -1;
        }

        $state->ball = $state->ball->moveToward($state->ballTarget, $state->ballSpeed * self::TICK);

        if ($state->ball->distanceTo($state->ballTarget) > 0.001) {
            return -1;
        }

        if ($state->ballGoal) {
            // possessing still holds the shooting side through the flight.
            return $state->possessing;
        }

        $receiver = $state->players[$state->ballTo] ?? null;
        if ($receiver !== null) {
            $state->carrierId = $receiver->id;
            $state->possessing = $receiver->side;
            $state->ball = $receiver->pos;
        }

        $state->ballTarget = null;
        $state->ballKind = 'idle';
        $state->holdTicks = 0;

        return -1;
    }

    /**
     * A defender who reaches the carrier contests the ball. Returns true when
     * possession turns over on the tackle.
     *
     * @param  list<MatchEvent>  $events
     */
    private function contest(PitchState $state, array &$events, Rng $rng, int $minute): bool
    {
        $carrier = $state->carrier();
        if ($carrier === null) {
            return false;
        }

        $defender = $this->nearestOpponent($state, $carrier->pos, $carrier->side);
        if ($defender === null || $carrier->pos->distanceTo($defender->pos) > self::TACKLE_RADIUS) {
            return false;
        }

        // A tackle is a low-probability attempt each tick a defender is on the
        // carrier, so a possession has time to breathe and pass out of pressure.
        $win = $rng->next() < 0.05 + max(0.0, $defender->attributes->tackling - $carrier->attributes->dribbling) / 400;
        if (! $win) {
            return false;
        }

        $type = $carrier->pos->distanceTo($this->goalOf($defender->side)) < 0.35
            ? EventType::Clearance
            : EventType::Tackle;
        $events[] = $this->event($minute, $type, $defender, null, $carrier->pos, null, true);

        $state->carrierId = $defender->id;
        $state->possessing = $defender->side;
        $state->ball = $defender->pos;
        $state->holdTicks = 0;

        return true;
    }

    /**
     * @param  list<MatchEvent>  $events
     */
    private function decide(PitchState $state, array &$events, Rng $rng, int $minute): void
    {
        $carrier = $state->carrier();
        if ($carrier === null) {
            return;
        }

        $goal = $this->goalOf($carrier->side);
        $distToGoal = $carrier->pos->distanceTo($goal);

        // In range: have a go, unless a much better-placed team-mate is free.
        if ($distToGoal < self::SHOOT_RANGE) {
            $inBox = $this->bestPassTarget($state, $carrier, $goal, $distToGoal, minProgress: 0.06);
            if ($inBox === null || $rng->next() < 0.6) {
                $this->shoot($state, $events, $rng, $minute, $carrier, $goal, $distToGoal);

                return;
            }

            $this->pass($state, $events, $rng, $minute, $carrier, $inBox);

            return;
        }

        // A through-ball into the space ahead of a runner in behind, the pass that
        // beats the block and makes a chance.
        if ($distToGoal < 0.55) {
            $runner = $this->bestRunner($state, $carrier, $goal, $distToGoal);
            if ($runner !== null && $rng->next() < 0.25) {
                $this->pass($state, $events, $rng, $minute, $carrier, $runner, $this->spaceAheadOf($runner, $goal));

                return;
            }
        }

        // Play forward when a progressive pass is on; otherwise carry into space,
        // and only recycle sideways when the way forward is blocked.
        $forward = $this->bestPassTarget($state, $carrier, $goal, $distToGoal, minProgress: 0.03);
        if ($forward !== null && $rng->next() < 0.85) {
            $this->pass($state, $events, $rng, $minute, $carrier, $forward);

            return;
        }

        if ($this->spaceAhead($state, $carrier)) {
            return; // dribble on, no event
        }

        $safe = $this->bestPassTarget($state, $carrier, $goal, $distToGoal, minProgress: -1.0);
        if ($safe !== null) {
            $this->pass($state, $events, $rng, $minute, $carrier, $safe);
        }

        // Nothing on: keep the ball and dribble next tick.
    }

    private function bestPassTarget(PitchState $state, PlayerState $carrier, Vec2 $goal, float $distToGoal, float $minProgress): ?PlayerState
    {
        $best = null;
        $bestScore = -INF;

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id || $mate->isGoalkeeper()) {
                continue;
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.04) {
                continue;
            }

            $progress = $distToGoal - $mate->pos->distanceTo($goal);
            if ($progress < $minProgress) {
                continue;
            }

            $opponent = $this->nearestOpponent($state, $mate->pos, $mate->side);
            $openness = $opponent === null ? 0.15 : min(0.15, $mate->pos->distanceTo($opponent->pos));
            if ($openness < 0.02) {
                continue; // too tightly marked to receive
            }

            $score = $progress * 1.2 + $openness * 0.6 - $reach * 0.2;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /** True when no opponent is close and goal-side of the carrier, so it can drive on. */
    private function spaceAhead(PitchState $state, PlayerState $carrier): bool
    {
        $goal = $this->goalOf($carrier->side);
        $carrierToGoal = $carrier->pos->distanceTo($goal);

        foreach ($state->players as $opponent) {
            if ($opponent->side === $carrier->side || $opponent->isGoalkeeper()) {
                continue;
            }

            if ($opponent->pos->distanceTo($goal) < $carrierToGoal
                && $opponent->pos->distanceTo($carrier->pos) < 0.12) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<MatchEvent>  $events
     */
    private function pass(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, PlayerState $target, ?Vec2 $into = null): void
    {
        // $into is the space a through-ball is played into; a normal pass goes to
        // the receiver's feet. Around 85% base completion for a competent passer
        // in space, dropping under pressure and when a defender sits in the lane.
        $dest = $into ?? $target->pos;
        $pressure = $this->pressure($state, $carrier);
        [$laneDefender, $laneDist] = $this->nearestOpponentToSegment($state, $carrier->pos, $dest, $carrier->side);
        $laneRisk = $laneDefender !== null && $laneDist < self::LANE_RADIUS
            ? (self::LANE_RADIUS - $laneDist) / self::LANE_RADIUS * self::LANE_WEIGHT
            : 0.0;
        $threshold = max(0.1, min(0.97, 0.55 + $carrier->attributes->passing / 100 * 0.38 - $pressure - $laneRisk));
        $success = $rng->next() <= $threshold;

        $interceptor = $success ? null : ($laneDefender ?? $this->nearestOpponent($state, $dest, $target->side));

        $events[] = $this->event($minute, EventType::Pass, $carrier, $target->id, $carrier->pos, $dest, $success);

        if (! $success && $interceptor !== null) {
            $type = $carrier->pos->distanceTo($this->goalOf($interceptor->side)) < 0.35
                ? EventType::Clearance
                : EventType::Interception;
            $events[] = $this->event($minute, $type, $interceptor, null, $dest, null, true);
        }

        $state->carrierId = PitchState::NO_CARRIER;
        $state->ballKind = 'pass';
        $state->ballSpeed = self::PASS_SPEED;

        if ($success) {
            // The receiver runs onto the ball (their feet, or the space in behind).
            $state->ballTarget = $dest;
            $state->ballTo = $target->id;
        } elseif ($interceptor !== null) {
            $state->ballTarget = $interceptor->pos;
            $state->ballTo = $interceptor->id;
        } else {
            $state->ballTarget = $dest;
            $state->ballTo = $target->id;
        }
    }

    /**
     * The best runner to slip in behind: a team-mate ahead of the ball with clear
     * space between him and the goal, so a through-ball can find him.
     */
    private function bestRunner(PitchState $state, PlayerState $carrier, Vec2 $goal, float $distToGoal): ?PlayerState
    {
        $best = null;
        $bestScore = 0.05;

        // The offside line: the deepest defending outfielder. A runner beyond it is
        // offside, so a through-ball to him would not stand.
        $lastLine = INF;
        foreach ($state->players as $defender) {
            if ($defender->side === $carrier->side || $defender->isGoalkeeper()) {
                continue;
            }
            $lastLine = min($lastLine, $defender->pos->distanceTo($goal));
        }

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id || $mate->isGoalkeeper()) {
                continue;
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.05) {
                continue;
            }

            $mateToGoal = $mate->pos->distanceTo($goal);
            if ($mateToGoal >= $distToGoal - 0.03) {
                continue; // must be ahead of the ball
            }

            if ($mateToGoal < $lastLine - 0.02) {
                continue; // offside, beyond the last defender
            }

            $space = $this->spaceAheadOf($mate, $goal);
            $cover = $this->nearestOpponent($state, $space, $mate->side);
            $openness = $cover === null ? 0.12 : min(0.12, $space->distanceTo($cover->pos));
            if ($openness < 0.04) {
                continue; // the space in behind is covered
            }

            $score = ($distToGoal - $mateToGoal) + $openness * 1.5 - $reach * 0.15;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /** A point a short way ahead of a runner, toward the goal, to play him in. */
    private function spaceAheadOf(PlayerState $runner, Vec2 $goal): Vec2
    {
        return $runner->pos->moveToward($goal, 0.1);
    }

    /**
     * @param  list<MatchEvent>  $events
     */
    private function shoot(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, Vec2 $goal, float $distToGoal): void
    {
        $keeper = $state->players[PlayerState::id(1 - $carrier->side, 0)] ?? null;
        $angle = 1.0 - abs($carrier->pos->y - 0.5) * 0.8;      // central shots are better
        $range = 1.0 - min(1.0, $distToGoal / self::SHOOT_RANGE) * 0.6;
        $keeperSave = $keeper !== null ? $keeper->attributes->tackling / 100 * 0.35 : 0.0;
        $threshold = max(0.03, min(0.7, $carrier->attributes->finishing / 100 * $angle * $range - $keeperSave));
        $goalScored = $rng->next() <= $threshold;

        $events[] = $this->event($minute, EventType::Shot, $carrier, null, $carrier->pos, null, $goalScored);

        $state->carrierId = PitchState::NO_CARRIER;
        $state->ballKind = 'shot';
        $state->ballSpeed = self::SHOT_SPEED;
        $state->ballTarget = $goal;
        $state->ballGoal = $goalScored;

        if ($goalScored) {
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        // Saved or off target: the keeper (or nearest defender) claims it.
        $claim = $keeper ?? $this->nearestOpponent($state, $goal, $carrier->side);
        if ($claim !== null) {
            $events[] = $this->event($minute, EventType::Save, $claim, null, $goal, null, true);
            $state->ballTo = $claim->id;
            $state->ballTarget = $claim->pos;
        } else {
            $state->ballTo = $carrier->id;
        }
    }

    private function pressed(PitchState $state): bool
    {
        $carrier = $state->carrier();
        if ($carrier === null) {
            return false;
        }

        $defender = $this->nearestOpponent($state, $carrier->pos, $carrier->side);

        return $defender !== null && $carrier->pos->distanceTo($defender->pos) < self::PRESS_RADIUS;
    }

    private function pressure(PitchState $state, PlayerState $carrier): float
    {
        $defender = $this->nearestOpponent($state, $carrier->pos, $carrier->side);
        if ($defender === null) {
            return 0.0;
        }

        $dist = $carrier->pos->distanceTo($defender->pos);

        return $dist >= self::MARK_RADIUS ? 0.0 : (self::MARK_RADIUS - $dist) / self::MARK_RADIUS * 0.35;
    }

    /**
     * The single attacker this defender is responsible for: its nearest man, but
     * only when it is the closest available defender to him (the presser is busy
     * on the carrier), so each runner is picked up once instead of the whole line
     * collapsing onto the ball.
     */
    private function markAssignment(PitchState $state, PlayerState $defender): ?PlayerState
    {
        $man = $this->nearestOpponent($state, $defender->pos, $defender->side);
        if ($man === null || $defender->pos->distanceTo($man->pos) > 0.25) {
            return null;
        }

        $carrier = $state->carrier();
        $owner = null;
        $ownerDist = INF;

        foreach ($state->players as $other) {
            if ($other->side !== $defender->side || $other->isGoalkeeper()) {
                continue;
            }

            if ($carrier !== null && $this->isNearestDefender($state, $other, $carrier->pos)) {
                continue; // the presser is committed to the carrier
            }

            $dist = $other->pos->distanceTo($man->pos);
            if ($dist < $ownerDist) {
                $ownerDist = $dist;
                $owner = $other;
            }
        }

        return $owner !== null && $owner->id === $defender->id ? $man : null;
    }

    private function isNearestDefender(PitchState $state, PlayerState $defender, Vec2 $point): bool
    {
        $nearest = $this->nearestOpponent($state, $point, 1 - $defender->side);

        return $nearest !== null && $nearest->id === $defender->id;
    }

    private function nearestOpponent(PitchState $state, Vec2 $point, int $side): ?PlayerState
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = $player->pos->distanceTo($point);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return $best;
    }

    /**
     * The opponent best placed to intercept a pass along the segment a→b, and its
     * distance to that lane.
     *
     * @return array{PlayerState|null, float}
     */
    private function nearestOpponentToSegment(PitchState $state, Vec2 $a, Vec2 $b, int $side): array
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = $this->segmentDistance($player->pos, $a, $b);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return [$best, $bestDist];
    }

    private function segmentDistance(Vec2 $point, Vec2 $a, Vec2 $b): float
    {
        $ab = $b->sub($a);
        $lengthSq = $ab->x * $ab->x + $ab->y * $ab->y;

        if ($lengthSq <= 0.0) {
            return $point->distanceTo($a);
        }

        $t = (($point->x - $a->x) * $ab->x + ($point->y - $a->y) * $ab->y) / $lengthSq;
        $t = max(0.0, min(1.0, $t));

        return $point->distanceTo($a->add($ab->scale($t)));
    }

    private function centralOutfielder(PitchState $state, int $side): ?PlayerState
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $player) {
            if ($player->side !== $side || $player->isGoalkeeper()) {
                continue;
            }

            $dist = $player->anchor->distanceTo(new Vec2(0.5, 0.5));
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $player;
            }
        }

        return $best;
    }

    private function goalOf(int $side): Vec2
    {
        return $side === 0 ? new Vec2(1.0, 0.5) : new Vec2(0.0, 0.5);
    }

    private function event(int $minute, EventType $type, PlayerState $actor, ?int $targetId, Vec2 $from, ?Vec2 $to, bool $success): MatchEvent
    {
        return new MatchEvent(
            $minute,
            $type,
            $actor->id,
            $targetId,
            $this->zone($from, $actor->side),
            $to !== null ? $this->zone($to, $actor->side) : null,
            $success,
            null,
            null,
        );
    }

    /** A side-oriented grid zone for feed/stat compatibility (advanced = higher x). */
    private function zone(Vec2 $point, int $side): Zone
    {
        $advance = $side === 0 ? $point->x : 1.0 - $point->x;

        return new Zone(
            max(0, min(Zone::MAX_X, (int) round($advance * Zone::MAX_X))),
            max(0, min(Zone::MAX_Y, (int) round($point->y * Zone::MAX_Y))),
        );
    }

    /**
     * @return array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>}
     */
    private function snapshot(PitchState $state, int $minute): array
    {
        $positions = [];
        foreach ($this->order() as $id) {
            $player = $state->players[$id] ?? null;
            $positions[] = $player !== null ? $player->pos->toArray() : [0.0, 0.0];
        }

        return [
            'm' => $minute,
            'b' => $state->ball->toArray(),
            'c' => $state->carrierId,
            's' => $state->possessing,
            'p' => $positions,
        ];
    }

    /**
     * The canonical player order for the position stream: home keeper then slots
     * 1..10, away keeper then slots 1..10.
     *
     * @return list<int>
     */
    private function order(): array
    {
        $ids = [];
        foreach ([0, 1] as $side) {
            foreach (range(0, 10) as $slot) {
                $ids[] = PlayerState::id($side, $slot);
            }
        }

        return $ids;
    }
}
