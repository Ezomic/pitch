<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;
use App\Sim\Domain\Roll;
use App\Sim\Engine\Rng;

/**
 * Players getting hurt in the match.
 *
 * No draw is taken from the match's Rng. At kickoff each outfielder gets, from a
 * stream of its own derived from the seed, the strain he can take before he is
 * hurt (his proneness) and how long it would keep him out. Strain then builds as
 * the match is played, from running on empty legs and from committed challenges,
 * so the chance depends on fatigue and tackling, a seed always gives the same
 * injuries, and a match in which nobody is hurt is the match it was before.
 *
 * Strain becomes a chance as strain / (1 + strain), i.e. a threshold of u / (1 - u)
 * for a uniform u, which is plain arithmetic and so identical on every machine.
 *
 * Goalkeepers cannot be hurt: the engine's keeper is a rating on the side, not a
 * body that runs or is challenged, and the squad has no reserve keeper to send on.
 */
final class Injuries
{
    // The three strain figures are calibrated together to about half an injury a
    // match, both sides together, split evenly between tired legs and challenges.

    /** Strain per tick on the move at completely spent legs, scaled by tiredness and by sprinting. */
    private const float FATIGUE_STRAIN = 0.00008;

    /** Strain on the man tackled by a defender who goes to ground, won or missed. */
    private const float SLIDE_STRAIN = 0.004;

    /** Strain on the man fouled, on top of the above: a mistimed challenge is the one that hurts. */
    private const float FOUL_STRAIN = 0.014;

    /** How much more a spent man takes from a challenge than a fresh one. */
    private const float CONTACT_TIRED = 1.0;

    /** The longest injury, in weeks. Most are short: see weeks(). */
    public const int MAX_WEEKS = 8;

    /** Ticks of stoppage while a hurt man who had the ball is helped off and play restarts. */
    private const int STOPPAGE_TICKS = 7;

    /** The legs a bench player brings on when a side replaces a hurt man itself. */
    private const float BENCH_STAMINA = 0.9;

    /** Mixed into the match seed so the injury stream never overlaps the match's own. */
    private const int STREAM = 0x1BADB002;

    /**
     * @param  array<int, PlayerState>  $players
     */
    public function assign(array $players, int $seed): void
    {
        $rng = new Rng($seed ^ self::STREAM);

        foreach ($players as $player) {
            if ($player->isGoalkeeper()) {
                continue;
            }

            $u = $rng->next();
            $player->proneness = $u / (1.0 - $u);
            $player->injuryWeeks = self::weeks($rng->next());
        }
    }

    /** Squared so that most injuries are a week or two and a long one is rare. */
    public static function weeks(float $u): int
    {
        return 1 + (int) floor($u * $u * self::MAX_WEEKS);
    }

    private static function risk(float $strain): float
    {
        return $strain / (1.0 + $strain);
    }

    public function run(PlayerState $player, bool $sprint): void
    {
        $effort = $sprint ? 1.0 + Movement::SPRINT_EXTRA : 1.0;
        $player->strain += $player->tiredness() * $effort * self::FATIGUE_STRAIN;
    }

    public function tackled(PlayerState $carrier, bool $slide, bool $foul = false): void
    {
        $strain = ($slide ? self::SLIDE_STRAIN : 0.0) + ($foul ? self::FOUL_STRAIN : 0.0);

        $carrier->strain += $strain * (1.0 + self::CONTACT_TIRED * $carrier->tiredness());
    }

    /**
     * Run once a tick, after everything that can add strain.
     *
     * @param  list<MatchEvent>  $events
     */
    public function resolve(PitchState $state, array &$events, int $minute): void
    {
        foreach ($state->players as $player) {
            if ($player->injured || ! $player->isPlaying() || $player->proneness === null || $player->strain < $player->proneness) {
                continue;
            }

            $player->injured = true;
            $roll = new Roll(0.0, $player->strain, 0.0, self::risk($player->strain), self::risk($player->proneness));
            $events[] = Events::of($minute, EventType::Injury, $player, null, $player->pos, null, false, null, $roll);

            if ($state->autoSubs[$player->side] && $state->subsLeft[$player->side] > 0) {
                $this->replace($state, $player);
            }
        }

        foreach ($state->players as $player) {
            if ($player->injured && ! $player->retired && ! $player->sentOff && $state->subsLeft[$player->side] <= 0) {
                $this->leave($state, $events, $minute, $player);
            }
        }
    }

    /**
     * Like for like: a side that makes its own changes sends on the same attributes
     * with fresher legs, so the opposition and a headless match are not a man down
     * for most of a match over a knock, yet still run out of changes after five.
     */
    private function replace(PitchState $state, PlayerState $player): void
    {
        $state->subsLeft[$player->side]--;
        $player->injured = false;
        $player->strain = 0.0;
        $player->stamina = self::BENCH_STAMINA;
    }

    /**
     * He cannot go on and cannot be replaced: he leaves the pitch and his side plays
     * the rest a man down. The ball he held, or the pass bound for him, goes to a team-mate.
     *
     * @param  list<MatchEvent>  $events
     */
    private function leave(PitchState $state, array &$events, int $minute, PlayerState $player): void
    {
        $events[] = Events::of($minute, EventType::InjuryOff, $player, null, $player->pos, null, false);

        $mate = $this->nearestTeammate($state, $player, $state->ballTarget ?? $player->pos);
        $from = $player->pos;
        $player->retired = true;
        $player->pos = new Vec2($from->x, $from->y < 0.5 ? 0.0 : 1.0);

        if ($mate === null) {
            return;
        }

        if ($state->carrierId === $player->id) {
            // A dead ball stays where it was placed: the team-mate walks to it.
            if ($state->deadBall > 0) {
                $mate->pos = $state->ball;
            } else {
                $state->ball = $mate->pos;
                $state->deadBall = self::STOPPAGE_TICKS;
                $state->teleported = true;
            }

            $state->carrierId = $mate->id;
            $state->holdTicks = 0;
        } elseif ($state->ballTo === $player->id) {
            $state->ballTo = $mate->id;
        }
    }

    private function nearestTeammate(PitchState $state, PlayerState $player, Vec2 $spot): ?PlayerState
    {
        $best = null;
        $bestDist = INF;

        foreach ($state->players as $mate) {
            if ($mate->side !== $player->side || $mate->id === $player->id || $mate->isGoalkeeper() || ! $mate->isPlaying()) {
                continue;
            }

            $dist = $mate->pos->distanceTo($spot);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $mate;
            }
        }

        return $best;
    }
}
