<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Decision;
use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Player;
use App\Sim\Domain\Position;
use App\Sim\Domain\Roll;
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

    private const float SHOOT_RANGE = 0.16;      // distance to goal a shot is viable from (calibrated: shots ~26/match)

    private const float MAX_PASS = 0.55;          // longest pass a player attempts

    private const float PRESS_RADIUS = 0.07;      // a defender this close forces a decision

    private const float TACKLE_RADIUS = 0.028;    // a defender this close contests the ball

    private const float MARK_RADIUS = 0.05;       // an opponent this close counts as marking

    private const float MARK = 0.45;              // how hard a defender shades onto its man

    private const float LANE_RADIUS = 0.04;       // a defender this near a pass lane can cut it

    private const float LANE_WEIGHT = 0.2;        // how much a covered lane suppresses a pass

    // The centre circle in normalised space. The pitch renders 3:2, so the circle
    // is an ellipse in 0..1 coordinates: wider across the goals than across the
    // width. No defender may stand inside it at a restart.
    public const float CIRCLE_RX = 0.087;

    public const float CIRCLE_RY = 0.13;

    private const int DEADBALL_TICKS = 7;         // pause while a set piece is taken

    private const float FOUL_CHANCE = 0.035;      // a mistimed tackle is sometimes a foul

    private const float THROW_CHANCE = 0.2;       // an errant pass that runs out for a throw

    private const float FREE_KICK_RANGE = 0.32;   // a foul this close to goal is a direct free kick

    private const float PENALTY_CONVERSION = 0.78;

    /**
     * @param  array<int, Player>  $home  slot id => player (ten outfielders)
     * @param  array<int, Player>  $away  slot id => player (ten outfielders)
     */
    public function simulate(array $home, array $away, int $seed, ?Formation $homeFormation = null, ?Formation $awayFormation = null): PitchResult
    {
        [$state, $rng] = $this->start($home, $away, $seed);

        return $this->resume($state, $rng, 0, self::TOTAL_TICKS);
    }

    /** How many ticks make up a full match, so callers can pace a live sim. */
    public function totalTicks(): int
    {
        return self::TOTAL_TICKS;
    }

    /**
     * The kickoff state and a fresh Rng for a match: the starting point a live
     * match persists and then advances a slice at a time.
     *
     * @param  array<int, Player>  $home
     * @param  array<int, Player>  $away
     * @return array{PitchState, Rng}
     */
    public function start(array $home, array $away, int $seed): array
    {
        return [$this->kickOff($this->buildStates($home, $away), side: 0), new Rng($seed)];
    }

    /**
     * Advance a match from $fromTick up to (not including) $toTick, mutating the
     * state and Rng in place and returning just this slice's events and frames.
     * Resuming in slices is byte-identical to one continuous simulate().
     */
    public function resume(PitchState $state, Rng $rng, int $fromTick, int $toTick): PitchResult
    {
        /** @var list<MatchEvent> $events */
        $events = [];
        /** @var list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}> $frames */
        $frames = [];

        $toTick = min($toTick, self::TOTAL_TICKS);
        for ($tick = $fromTick; $tick < $toTick; $tick++) {
            $state = $this->tick($state, $rng, $tick, $events, $frames);
        }

        return new PitchResult($events, $frames, $state->homeGoals, $state->awayGoals, $state);
    }

    /**
     * One tick of the match. Returns the state to carry into the next tick, which
     * is a fresh kickoff state after a goal.
     *
     * @param  list<MatchEvent>  $events
     * @param  list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>  $frames
     */
    private function tick(PitchState $state, Rng $rng, int $tick, array &$events, array &$frames): PitchState
    {
        // A goal on the previous tick left the ball in the net for that frame and
        // owes a kickoff; take it now so the restart is its own frame.
        if ($state->pendingKickoff !== null) {
            $restart = $this->kickOff($state->players, side: $state->pendingKickoff);
            $restart->homeGoals = $state->homeGoals;
            $restart->awayGoals = $state->awayGoals;
            $state = $restart; // kickOff marks this frame as a placed-ball (teleported) restart
        } else {
            $state->teleported = false;
        }

        $state->justScored = -1;
        $minute = (int) min(89, $tick / self::TOTAL_TICKS * 90);

        $this->setTargets($state, $tick);
        $this->moveAll($state);

        if ($state->inFlight()) {
            $scorer = $this->advanceBall($state, $events, $minute);

            if ($scorer >= 0) {
                // Keep the ball where it crossed the line and mark this frame as the
                // goal; the kickoff waits for the next tick so the net is shown first.
                $scorer === 0 ? $state->homeGoals++ : $state->awayGoals++;
                $state->justScored = $scorer;
                $state->pendingKickoff = 1 - $scorer;
            }
        } else {
            $carrier = $state->carrier();

            if ($carrier !== null && $state->deadBall > 0) {
                // A set piece is being taken: the ball sits at the taker's feet for
                // a beat before play resumes, uncontested.
                $state->deadBall--;
                $state->ball = $carrier->pos;
            } elseif ($carrier !== null) {
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

        return $state;
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
        // away side is mirrored. Deep players stay home; the forward line sits
        // higher so attacks have bodies near the opponent box to build onto.
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
        $state->teleported = true; // the ball is placed on the centre spot

        // No defender may stand inside the centre circle at the restart; push any
        // that do out to its edge.
        $defending = 1 - $side;
        foreach ($state->players as $player) {
            if ($player->side === $defending && ! $player->isGoalkeeper()) {
                $this->pushOutsideCentreCircle($player, $defending);
            }
        }

        // The kicking-off side taps off: a central player on the spot, with a
        // second dropping just behind to receive.
        [$passer, $receiver] = $this->kickoffPair($state, $side);
        if ($passer !== null) {
            $passer->pos = new Vec2(0.5, 0.5);
            $state->carrierId = $passer->id;
            $state->ball = $passer->pos;
        }
        if ($receiver !== null) {
            $receiver->pos = new Vec2($side === 0 ? 0.46 : 0.54, 0.5);
        }

        return $state;
    }

    /**
     * Move a defender that has strayed inside the centre circle radially out to
     * its edge; a player exactly on the spot retreats toward its own goal.
     */
    private function pushOutsideCentreCircle(PlayerState $player, int $defendingSide): void
    {
        $dx = $player->pos->x - 0.5;
        $dy = $player->pos->y - 0.5;
        $norm = ($dx / self::CIRCLE_RX) ** 2 + ($dy / self::CIRCLE_RY) ** 2;

        if ($norm >= 1.0) {
            return;
        }

        if ($norm <= 1e-9) {
            $dx = $defendingSide === 0 ? -self::CIRCLE_RX : self::CIRCLE_RX;
            $dy = 0.0;
            $norm = 1.0;
        }

        // A hair beyond the edge so the player is unambiguously outside the circle.
        $scale = 1.02 / sqrt($norm);
        $player->pos = (new Vec2(0.5 + $dx * $scale, 0.5 + $dy * $scale))->clampToPitch();
    }

    /**
     * The two most central outfielders of a side, by formation anchor: the passer
     * who taps off and the team-mate dropping in to receive.
     *
     * @return array{?PlayerState, ?PlayerState}
     */
    private function kickoffPair(PitchState $state, int $side): array
    {
        $centre = new Vec2(0.5, 0.5);
        $outfield = [];
        foreach ($state->players as $player) {
            if ($player->side === $side && ! $player->isGoalkeeper()) {
                $outfield[] = $player;
            }
        }

        usort(
            $outfield,
            fn (PlayerState $a, PlayerState $b): int => $a->anchor->distanceTo($centre) <=> $b->anchor->distanceTo($centre),
        );

        return [$outfield[0] ?? null, $outfield[1] ?? null];
    }

    private function setTargets(PitchState $state, int $tick): void
    {
        $ballX = $state->ball->x;

        foreach ($state->players as $player) {
            if ($player->isGoalkeeper()) {
                $goalX = $player->side === 0 ? 0.03 : 0.97;
                $player->target = new Vec2($goalX, 0.5 + ($state->ball->y - 0.5) * 0.4);

                continue;
            }

            if ($player->id === $state->carrierId) {
                $goal = $this->goalOf($player->side);

                // A wide player in the attacking third attacks the byline to cross,
                // rather than cutting inside, unless he is clean through, in which
                // case he drives straight at goal to shoot.
                if (abs($player->pos->y - 0.5) > 0.22 && $player->pos->distanceTo($goal) < 0.5
                    && ! $this->clearRunToGoal($state, $player, $goal)) {
                    $bylineX = $player->side === 0 ? 0.95 : 0.05;
                    $wideY = $player->pos->y < 0.5 ? 0.12 : 0.88;
                    $player->target = new Vec2($bylineX, $wideY);

                    continue;
                }

                // Otherwise drive at goal, veering wide of a closing defender rather
                // than running straight into the tackle.
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
                // In possession the whole block steps up the pitch as the team
                // builds: the back line and midfield push up toward the halfway
                // line, while the forwards play high on the shoulder of the
                // opponent's last defender, ready to receive around their defence.
                $bias = $this->mentalityBias($state, $player->side);
                $ballAdvance = $player->side === 0 ? $ballX : 1.0 - $ballX;
                $push = max(0.0, $ballAdvance - 0.15) * (1.0 + 0.22 * $bias);
                $dir = $player->side === 0 ? 1.0 : -1.0;

                if ($player->position === Position::Forward) {
                    $lineX = $this->opponentLastLineX($state, $player->side);
                    $shoulderX = $lineX - $dir * 0.10; // sit off the last man, not glued on
                    $anchorPushX = $player->anchor->x + $dir * $push * 0.45;
                    $targetX = $dir > 0 ? max($anchorPushX, $shoulderX) : min($anchorPushX, $shoulderX);
                } else {
                    $lift = $push * ($player->position === Position::Midfielder ? 0.85 : 0.6);
                    $targetX = $player->anchor->x + $dir * $lift;

                    if ($player->position === Position::Defender) {
                        // Hold a back line no higher than around the halfway line so
                        // the team is not caught square on the turnover.
                        $targetX = $dir > 0 ? min($targetX, 0.52) : max($targetX, 0.48);
                    }
                }

                $base = new Vec2(
                    $targetX,
                    $player->anchor->y + ($state->ball->y - 0.5) * 0.2,
                );

                // Off-ball run: every so often, if the space ahead toward goal is
                // free of a defender, a forward or midfielder bursts into it to be
                // played in behind, rather than holding the line. Runs are staggered
                // per player and deterministic (no random draw), so the shape breaks
                // and reforms instead of everyone lurching at once.
                if ($player->id !== $state->carrierId
                    && $player->position === Position::Forward
                    && $this->runWindow($player, $tick, $this->mentalityBias($state, $player->side))) {
                    $goal = $this->goalOf($player->side);
                    $ahead = $base->moveToward($goal, 0.14);
                    if ($this->spaceFreeAt($state, $ahead, $player->side)) {
                        $base = $ahead;
                    }
                }

                $player->target = $base->clampToPitch();

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

            // Leave the forwards high as a counter outlet. Rather than tracking back
            // into the block, they hold up the pitch between the opponent's midfield
            // and defensive lines, so winning the ball springs an immediate attack
            // with a target already in behind instead of building from deep.
            if ($player->position === Position::Forward) {
                $line = $this->opponentLastLineX($state, $player->side);
                $holdX = $player->side === 0
                    ? min(max($line - 0.08, 0.52), 0.66)
                    : max(min($line + 0.08, 0.48), 0.34);
                $player->target = (new Vec2(
                    $holdX,
                    $player->anchor->y + ($state->ball->y - 0.5) * 0.1,
                ))->clampToPitch();

                continue;
            }

            // Hold a compact block goal-side of the ball. A meaningfully higher line
            // needs timed off-ball runs to exploit the space it concedes, which the
            // engine does not model yet, so stepping it up here only strangles the
            // build-up; that is deferred to the off-ball-runs stage.
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

    /**
     * A deterministic, staggered window during which a player is minded to make a
     * run: a few of them at any moment, spread out by id, so runs come "every so
     * often" rather than all together. No random draw, to keep determinism.
     */
    private function runWindow(PlayerState $player, int $tick, int $bias = 0): bool
    {
        return (intdiv($tick, 10) + $player->id) % 12 < 2 + $bias;
    }

    /** Mentality as a bias: +1 attacking, 0 balanced, -1 defensive. */
    private function mentalityBias(PitchState $state, int $side): int
    {
        return match ($state->mentality($side)) {
            'attacking' => 1,
            'defensive' => -1,
            default => 0,
        };
    }

    /** True when no opponent is close to the point, so it is space to run into. */
    private function spaceFreeAt(PitchState $state, Vec2 $point, int $side): bool
    {
        foreach ($state->players as $player) {
            if ($player->side === $side || $player->isGoalkeeper()) {
                continue;
            }

            if ($player->pos->distanceTo($point) < 0.07) {
                return false;
            }
        }

        return true;
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

        // A missed shot that has reached goal turns into its set piece: a corner or
        // a goal kick, taken after the ball is repositioned.
        if ($state->pendingType !== null && $state->pendingSpot !== null) {
            $type = $state->pendingType;
            $side = $state->pendingSide;
            $spot = $state->pendingSpot;
            $state->pendingType = null;
            $state->pendingSpot = null;
            $this->awardRestart($state, $events, $minute, $type, $side, $spot);

            return -1;
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
            // A mistimed challenge is sometimes a foul. Where it happens decides the
            // restart: a penalty in the box, a direct free kick just outside it, or
            // a possession free kick anywhere else.
            if ($rng->next() < self::FOUL_CHANCE) {
                $this->foul($state, $events, $rng, $minute, $carrier, $defender);

                return true;
            }

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

        // Clean through on the keeper: shoot if close enough, otherwise drive
        // straight at goal. This comes first so a player who has beaten the line
        // never crosses, switches wide or lays it off from a one-on-one.
        if ($this->clearRunToGoal($state, $carrier, $goal)) {
            if ($distToGoal < self::SHOOT_RANGE + 0.06) {
                $this->shoot($state, $events, $rng, $minute, $carrier, $goal, $distToGoal);
            }

            return; // otherwise carry on at goal (the carrier already drives at it)
        }

        // Wide and advanced: whip a cross into the box instead of cutting inside.
        // Wing play is a real route to goal, not just a way back into the middle.
        if ($distToGoal < 0.5 && abs($carrier->pos->y - 0.5) > 0.24) {
            $target = $this->crossTarget($state, $carrier, $goal);
            if ($target !== null && $rng->next() < 0.65) {
                $this->cross($state, $events, $rng, $minute, $carrier, $target);

                return;
            }
        }

        // Central and advanced but NOT in shooting range: switch it out to a winger
        // in space to attack the flank. But an unmarked player with clear space
        // ahead drives at the goal himself rather than turning it back out wide, so
        // a forward through on a channel keeps running instead of laying it off.
        if ($distToGoal > self::SHOOT_RANGE + 0.07 && $distToGoal < 0.72 && abs($carrier->pos->y - 0.5) < 0.3) {
            $wide = $this->wideOutlet($state, $carrier, $goal, $distToGoal);
            // With space ahead the carrier mostly drives on himself; only now and
            // then does he still switch it wide, so he does not turn back out from a
            // promising run, but the play keeps some variety.
            $switchChance = $this->spaceAhead($state, $carrier, 0.24) ? 0.25 : 0.6;
            if ($wide !== null && $rng->next() < $switchChance) {
                $this->pass($state, $events, $rng, $minute, $carrier, $wide);

                return;
            }
        }

        // In range: shoot a genuine chance (central, close, a clear sight of goal),
        // otherwise work a better ball. Speculative shots from tight angles or a
        // blocked lane are what turn a match into a shooting gallery, so only take
        // them when pressed with nothing on.
        if ($distToGoal < self::SHOOT_RANGE) {
            $quality = $this->shotQuality($state, $carrier, $goal, $distToGoal);
            $inBox = $this->bestPassTarget($state, $carrier, $goal, $distToGoal, minProgress: 0.02);

            // A decent chance: shoot. (A genuine clean-through has already been
            // handled at the top of decide, so this is the in-a-crowd case.)
            if ($quality > 0.58 && ($inBox === null || $rng->next() < 0.6)) {
                $this->shoot($state, $events, $rng, $minute, $carrier, $goal, $distToGoal);

                return;
            }

            if ($inBox !== null && $rng->next() < 0.7) {
                $this->pass($state, $events, $rng, $minute, $carrier, $inBox);

                return;
            }

            if ($this->pressed($state)) {
                // Forced: get the shot away rather than lose it in the box.
                $this->shoot($state, $events, $rng, $minute, $carrier, $goal, $distToGoal);

                return;
            }

            // Blocked in the box with no shot: work it back out and rebuild, rather
            // than force a half-chance. A genuine run at goal was already taken above,
            // so this never turns down an open shot.
            $outlet = $this->safestOutlet($state, $carrier);
            if ($outlet !== null) {
                $this->pass($state, $events, $rng, $minute, $carrier, $outlet);

                return;
            }

            return;
        }

        // A through-ball into the space ahead of a runner in behind, the pass that
        // beats a high line and makes a chance. Played readily, because the space
        // behind a stepped-up defence is exactly what it is there to punish.
        if ($distToGoal < 0.6) {
            $runner = $this->bestRunner($state, $carrier, $goal, $distToGoal);
            if ($runner !== null && $rng->next() < 0.28) {
                $this->pass($state, $events, $rng, $minute, $carrier, $runner, $this->spaceAheadOf($runner, $goal));

                return;
            }
        }

        // Build-up. Progress when a genuinely open forward option is on, but do not
        // force it: keeping the ball is usually better than a hopeful ball into
        // traffic. With nothing forward, recycle to the safest open team-mate in
        // any direction, a defender or the keeper included. That circulation is
        // what real build-up is, and it pulls the whole team into the game instead
        // of the forwards knocking it among themselves.
        $pressed = $this->pressed($state);
        $forward = $this->bestPassTarget($state, $carrier, $goal, $distToGoal, minProgress: 0.03);

        if ($forward !== null && $rng->next() < ($pressed ? 0.62 : 0.88)) {
            $this->pass($state, $events, $rng, $minute, $carrier, $forward);

            return;
        }

        if (! $pressed && $this->spaceAhead($state, $carrier)) {
            return; // drive into the space ahead
        }

        $outlet = $this->safestOutlet($state, $carrier);
        if ($outlet !== null) {
            $this->pass($state, $events, $rng, $minute, $carrier, $outlet);

            return;
        }

        // Nothing on at all: hold and dribble next tick.
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

            // Prefer the shorter progressive pass through the lines (defence to
            // midfield to attack) over a long ball that skips a line: cap the reward
            // for sheer distance gained and lean on keeping it short, so the midfield
            // is the connecting point rather than being bypassed.
            $score = min($progress, 0.32) * 1.4 + $openness * 0.6 - $reach * 0.5;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /**
     * The safest team-mate to keep the ball with, in any direction: the most open
     * man with a clear lane, mildly preferring not to retreat too far toward the
     * own goal and not to over-hit it. Unlike a progressive pass this includes the
     * deeper players and the keeper, so a pressed carrier can recycle possession
     * rather than lose it forcing the ball forward.
     */
    private function safestOutlet(PitchState $state, PlayerState $carrier): ?PlayerState
    {
        $goal = $this->goalOf($carrier->side);
        $carrierToGoal = $carrier->pos->distanceTo($goal);

        $best = null;
        $bestScore = -INF;

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id) {
                continue;
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.04) {
                continue;
            }

            $opponent = $this->nearestOpponent($state, $mate->pos, $mate->side);
            $openness = $opponent === null ? 0.2 : min(0.2, $mate->pos->distanceTo($opponent->pos));
            if ($openness < 0.03) {
                continue; // too tightly marked to be a safe outlet
            }

            [$laneDefender, $laneDist] = $this->nearestOpponentToSegment($state, $carrier->pos, $mate->pos, $carrier->side);
            if ($laneDefender !== null && $laneDist < 0.022) {
                continue; // the ball would be cut out
            }

            $retreat = max(0.0, $mate->pos->distanceTo($goal) - $carrierToGoal);
            $score = $openness - $retreat * 1.2 - $reach * 0.1;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /**
     * True when the carrier has beaten the defensive line: no outfield opponent is
     * meaningfully closer to goal than it is, only the keeper to beat. A genuine
     * clean-through, where it must drive and shoot rather than turn back. Kept
     * strict so it fires only on a real chance, not merely an open passing lane.
     */
    private function clearRunToGoal(PitchState $state, PlayerState $carrier, Vec2 $goal): bool
    {
        $carrierToGoal = $carrier->pos->distanceTo($goal);
        if ($carrierToGoal > 0.5) {
            return false; // too far out to be through on goal
        }

        foreach ($state->players as $opponent) {
            if ($opponent->side === $carrier->side || $opponent->isGoalkeeper()) {
                continue;
            }

            if ($opponent->pos->distanceTo($goal) < $carrierToGoal + 0.05) {
                return false; // an outfielder is level with or goal-side of the carrier
            }
        }

        return true;
    }

    /** True when no opponent is close and goal-side of the carrier, so it can drive on. */
    private function spaceAhead(PitchState $state, PlayerState $carrier, float $radius = 0.12): bool
    {
        $goal = $this->goalOf($carrier->side);
        $carrierToGoal = $carrier->pos->distanceTo($goal);

        foreach ($state->players as $opponent) {
            if ($opponent->side === $carrier->side || $opponent->isGoalkeeper()) {
                continue;
            }

            if ($opponent->pos->distanceTo($goal) < $carrierToGoal
                && $opponent->pos->distanceTo($carrier->pos) < $radius) {
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
        $draw = $rng->next();
        $success = $draw <= $threshold;

        $decision = $this->buildDecision($state, $carrier, $this->danger($dest, $carrier->side));
        $roll = new Roll(0.55, $carrier->attributes->passing / 100 * 0.38, $pressure + $laneRisk, $threshold, $draw);
        $events[] = $this->event($minute, EventType::Pass, $carrier, $target->id, $carrier->pos, $dest, $success, $decision, $roll);

        // An errant ball sometimes runs out of play for a throw-in to the other side.
        if (! $success && $rng->next() < self::THROW_CHANCE) {
            $spot = new Vec2(min(0.97, max(0.03, $dest->x)), $dest->y < 0.5 ? 0.02 : 0.98);
            $this->awardRestart($state, $events, $minute, EventType::ThrowIn, 1 - $carrier->side, $spot);

            return;
        }

        $interceptor = $success ? null : ($laneDefender ?? $this->nearestOpponent($state, $dest, $target->side));

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
     * The best team-mate to cross to: a runner arriving centrally in the box, the
     * more open and the closer to goal the better.
     */
    private function crossTarget(PitchState $state, PlayerState $carrier, Vec2 $goal): ?PlayerState
    {
        $best = null;
        $bestScore = -INF;

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id || $mate->isGoalkeeper()) {
                continue;
            }

            if ($mate->pos->distanceTo($goal) > 0.24 || abs($mate->pos->y - 0.5) > 0.3) {
                continue; // must be central and in or near the box
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.08) {
                continue;
            }

            $marker = $this->nearestOpponent($state, $mate->pos, $mate->side);
            $openness = $marker === null ? 0.12 : min(0.12, $mate->pos->distanceTo($marker->pos));
            $score = $openness - $mate->pos->distanceTo($goal);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /**
     * An open winger in an advanced wide position to switch play to, so a central
     * carrier can spread the ball to the flank rather than force it through the
     * middle. Must be wide, not much behind the ball, and in space.
     */
    private function wideOutlet(PitchState $state, PlayerState $carrier, Vec2 $goal, float $distToGoal): ?PlayerState
    {
        $best = null;
        $bestScore = -INF;

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id || $mate->isGoalkeeper()) {
                continue;
            }

            if (abs($mate->pos->y - 0.5) < 0.28) {
                continue; // not wide enough to be a flank outlet
            }

            if ($mate->pos->distanceTo($goal) > $distToGoal + 0.16) {
                continue; // too far behind the ball
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.1) {
                continue;
            }

            $marker = $this->nearestOpponent($state, $mate->pos, $mate->side);
            $openness = $marker === null ? 0.15 : min(0.15, $mate->pos->distanceTo($marker->pos));
            if ($openness < 0.04) {
                continue; // marked, no point switching into him
            }

            $score = $openness - $reach * 0.15;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $mate;
            }
        }

        return $best;
    }

    /**
     * Whip a cross into the box. Crosses are hit-or-miss: they find their target
     * less often than a normal pass, and a defender heads clear when they miss.
     *
     * @param  list<MatchEvent>  $events
     */
    private function cross(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, PlayerState $target): void
    {
        $success = $rng->next() < 0.45;
        $events[] = $this->event($minute, EventType::Cross, $carrier, $target->id, $carrier->pos, $target->pos, $success);

        $state->carrierId = PitchState::NO_CARRIER;
        $state->ballKind = 'pass';
        $state->ballSpeed = self::PASS_SPEED;

        if ($success) {
            $state->ballTarget = $target->pos;
            $state->ballTo = $target->id;

            return;
        }

        $defender = $this->nearestOpponent($state, $target->pos, $target->side);
        if ($defender !== null) {
            $events[] = $this->event($minute, EventType::Clearance, $defender, null, $target->pos, null, true);
            $state->ballTarget = $defender->pos;
            $state->ballTo = $defender->id;

            return;
        }

        $state->ballTarget = $target->pos;
        $state->ballTo = $target->id;
    }

    /**
     * How good a chance this is, 0..1: central angle, close range and a clear
     * sight of goal (no defender sitting in the shot lane). Drives both whether
     * to shoot and how likely it is to go in, so the two agree.
     */
    private function shotQuality(PitchState $state, PlayerState $carrier, Vec2 $goal, float $distToGoal): float
    {
        $angle = 1.0 - abs($carrier->pos->y - 0.5) * 1.2;   // central shots are far better
        $range = 1.0 - min(1.0, $distToGoal / self::SHOOT_RANGE);
        [$blocker, $laneDist] = $this->nearestOpponentToSegment($state, $carrier->pos, $goal, $carrier->side);
        $clear = $blocker !== null && $laneDist < 0.05 ? $laneDist / 0.05 : 1.0;

        return max(0.0, $angle) * $range * $clear;
    }

    /**
     * @param  list<MatchEvent>  $events
     */
    private function shoot(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, Vec2 $goal, float $distToGoal): void
    {
        $keeper = $state->players[PlayerState::id(1 - $carrier->side, 0)] ?? null;
        $quality = $this->shotQuality($state, $carrier, $goal, $distToGoal);
        $keeperSave = $keeper !== null ? $keeper->attributes->tackling / 100 * 0.33 : 0.0;
        $threshold = max(0.02, min(0.5, $carrier->attributes->finishing / 100 * $quality * 0.60 - $keeperSave));
        $draw = $rng->next();
        $goalScored = $draw <= $threshold;

        $decision = $this->buildDecision($state, $carrier, $quality);
        $roll = new Roll(0.0, $carrier->attributes->finishing / 100 * $quality * 0.60, $keeperSave, $threshold, $draw);
        $events[] = $this->event($minute, EventType::Shot, $carrier, null, $carrier->pos, null, $goalScored, $decision, $roll);

        $state->carrierId = PitchState::NO_CARRIER;
        $state->ballKind = 'shot';
        $state->ballSpeed = self::SHOT_SPEED;
        $state->ballTarget = $goal;
        $state->ballGoal = $goalScored;

        if ($goalScored) {
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        // A miss resolves once the ball reaches goal: the keeper saves and holds,
        // it deflects behind for a corner, or it is off target for a goal kick.
        $roll = $rng->next();
        if ($roll < 0.4 && $keeper !== null) {
            // Saved and held: the keeper claims it and plays on.
            $events[] = $this->event($minute, EventType::Save, $keeper, null, $goal, null, true);
            $state->ballTo = $keeper->id;
            $state->ballTarget = $keeper->pos;
        } elseif ($roll < 0.65) {
            // Deflected behind for a corner to the attacking side.
            $state->pendingType = EventType::Corner;
            $state->pendingSide = $carrier->side;
            $state->pendingSpot = $this->cornerSpot($carrier->side, $carrier->pos->y);
            $state->ballTo = PitchState::NO_CARRIER;
        } else {
            // Off target: a goal kick to the defending side.
            $state->pendingType = EventType::GoalKick;
            $state->pendingSide = 1 - $carrier->side;
            $state->pendingSpot = $this->goalKickSpot(1 - $carrier->side);
            $state->ballTo = PitchState::NO_CARRIER;
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
        if ($man === null || $defender->pos->distanceTo($man->pos) > 0.35) {
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

    /**
     * The x of the opponent's deepest outfielder, the last line an attacking side
     * plays off. For side 0 (attacking toward x=1) that is the largest opponent x;
     * for side 1 the smallest.
     */
    private function opponentLastLineX(PitchState $state, int $attackingSide): float
    {
        $line = null;

        foreach ($state->players as $player) {
            if ($player->side === $attackingSide || $player->isGoalkeeper()) {
                continue;
            }

            $line = $line === null
                ? $player->pos->x
                : ($attackingSide === 0 ? max($line, $player->pos->x) : min($line, $player->pos->x));
        }

        return $line ?? ($attackingSide === 0 ? 0.9 : 0.1);
    }

    /**
     * Award a set piece: emit its event, place the ball at the restart spot with a
     * taker of the awarded side on it, and hold play for a beat so it reads as a
     * dead ball. A goal kick is taken by the keeper, everything else by the nearest
     * team-mate to the spot.
     *
     * @param  list<MatchEvent>  $events
     */
    private function awardRestart(PitchState $state, array &$events, int $minute, EventType $type, int $side, Vec2 $spot, ?PlayerState $creditActor = null): void
    {
        $taker = $type === EventType::GoalKick
            ? ($state->players[PlayerState::id($side, 0)] ?? null)
            : $this->nearestTeammateTo($state, $side, $spot);

        if ($taker === null) {
            return;
        }

        $events[] = $this->event($minute, $type, $creditActor ?? $taker, null, $spot, null, true);

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

    /** The corner flag on the attacking side, on the near touchline to the shot. */
    private function cornerSpot(int $attackingSide, float $y): Vec2
    {
        return new Vec2($attackingSide === 0 ? 0.99 : 0.01, $y < 0.5 ? 0.02 : 0.98);
    }

    /** In front of the defending side's own goal, where its keeper restarts. */
    private function goalKickSpot(int $defendingSide): Vec2
    {
        return new Vec2($defendingSide === 0 ? 0.08 : 0.92, 0.5);
    }

    /**
     * Resolve a foul by where it happened: a penalty inside the box, a direct free
     * kick within range of goal, or a possession free kick anywhere else.
     *
     * @param  list<MatchEvent>  $events
     */
    private function foul(PitchState $state, array &$events, Rng $rng, int $minute, PlayerState $carrier, PlayerState $defender): void
    {
        $goal = $this->goalOf($carrier->side);

        if ($this->inPenaltyBox($carrier->pos, $carrier->side)) {
            $this->penalty($state, $events, $rng, $minute, $carrier);

            return;
        }

        if ($carrier->pos->distanceTo($goal) < self::FREE_KICK_RANGE) {
            $events[] = $this->event($minute, EventType::Foul, $defender, null, $carrier->pos, null, true);
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
        $spot = $this->penaltySpot($side);
        $taker = $this->nearestTeammateTo($state, $side, $spot) ?? $winner;
        $goal = $this->goalOf($side);
        $keeper = $state->players[PlayerState::id(1 - $side, 0)] ?? null;

        $events[] = $this->event($minute, EventType::Penalty, $taker, null, $spot, null, true);

        $scored = $rng->next() < self::PENALTY_CONVERSION;
        $events[] = $this->event($minute, EventType::Shot, $taker, null, $spot, null, $scored);

        $taker->pos = $spot;
        $state->carrierId = PitchState::NO_CARRIER;
        $state->possessing = $side;
        $state->ball = $spot;
        $state->ballKind = 'shot';
        $state->ballSpeed = self::SHOT_SPEED;
        $state->ballGoal = $scored;

        if ($scored || $keeper === null) {
            $state->ballTarget = $goal;
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        $events[] = $this->event($minute, EventType::Save, $keeper, null, $goal, null, true);
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
        $events[] = $this->event($minute, EventType::Shot, $winner, null, $spot, null, $scored);

        $state->carrierId = PitchState::NO_CARRIER;
        $state->possessing = $side;
        $state->ball = $spot;
        $state->ballKind = 'shot';
        $state->ballSpeed = self::SHOT_SPEED;
        $state->ballGoal = $scored;

        if ($scored) {
            $state->ballTarget = $goal;
            $state->ballTo = PitchState::NO_CARRIER;

            return;
        }

        if ($rng->next() < 0.5 && $keeper !== null) {
            $events[] = $this->event($minute, EventType::Save, $keeper, null, $goal, null, true);
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

    private function inPenaltyBox(Vec2 $pos, int $attackingSide): bool
    {
        $inX = $attackingSide === 0 ? $pos->x > 0.83 : $pos->x < 0.17;

        return $inX && $pos->y > 0.21 && $pos->y < 0.79;
    }

    private function penaltySpot(int $attackingSide): Vec2
    {
        return new Vec2($attackingSide === 0 ? 0.88 : 0.12, 0.5);
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

    private function goalOf(int $side): Vec2
    {
        return $side === 0 ? new Vec2(1.0, 0.5) : new Vec2(0.0, 0.5);
    }

    private function event(int $minute, EventType $type, PlayerState $actor, ?int $targetId, Vec2 $from, ?Vec2 $to, bool $success, ?Decision $decision = null, ?Roll $roll = null): MatchEvent
    {
        return new MatchEvent(
            $minute,
            $type,
            $actor->id,
            $targetId,
            $this->zone($from, $actor->side),
            $to !== null ? $this->zone($to, $actor->side) : null,
            $success,
            $decision,
            $roll,
        );
    }

    /**
     * How dangerous it is to have the ball at a point: 1 on the goal line, fading
     * to 0 by ~0.7 of the pitch away. A shared yardstick for comparing the options
     * a carrier weighs, so a decision can be audited after the fact.
     */
    private function danger(Vec2 $pos, int $side): float
    {
        $dist = $pos->distanceTo($this->goalOf($side));

        return max(0.0, min(1.0, 1.0 - $dist / 0.7));
    }

    /**
     * A record of what the carrier could see and how the chosen action compared:
     * how many team-mates were reachable, how many had a clear lane, the threat of
     * the action taken and the best threat on offer.
     */
    private function buildDecision(PitchState $state, PlayerState $carrier, float $chosenThreat): Decision
    {
        $goal = $this->goalOf($carrier->side);
        $distToGoal = $carrier->pos->distanceTo($goal);

        /** @var list<array{float, bool}> $options threat, clear-lane */
        $options = [];

        if ($distToGoal < self::SHOOT_RANGE + 0.06) {
            $options[] = [$this->shotQuality($state, $carrier, $goal, $distToGoal), true];
        }

        foreach ($state->players as $mate) {
            if ($mate->side !== $carrier->side || $mate->id === $carrier->id || $mate->isGoalkeeper()) {
                continue;
            }

            $reach = $carrier->pos->distanceTo($mate->pos);
            if ($reach > self::MAX_PASS || $reach < 0.04) {
                continue;
            }

            [$laneDefender, $laneDist] = $this->nearestOpponentToSegment($state, $carrier->pos, $mate->pos, $carrier->side);
            $clear = $laneDefender === null || $laneDist >= self::LANE_RADIUS;
            $options[] = [$this->danger($mate->pos, $carrier->side), $clear];
        }

        $visible = array_values(array_filter($options, fn (array $o): bool => $o[1]));
        $bestVisible = array_map(fn (array $o): float => $o[0], $visible);

        return new Decision(
            optionsVisible: count($visible),
            optionsTotal: count($options),
            chosenThreat: $chosenThreat,
            bestAvailableThreat: $bestVisible === [] ? $chosenThreat : max($bestVisible),
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
     * @return array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}
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
            'j' => $state->teleported,
            'goal' => $state->justScored,
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
