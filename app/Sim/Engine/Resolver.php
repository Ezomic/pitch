<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\Roll;
use App\Sim\Domain\Zone;

final class Resolver
{
    private const float SKILL_SCALE = 100.0;

    private const float PRESSURE_WEIGHT = 0.30;

    // How much of a striker's finishing carries into an attempt on goal.
    private const float SHOT_ACCURACY = 0.9;

    private const float HEADER_ACCURACY = 0.78;

    public function resolve(Option $option, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias = 1.0): ResolveOutcome
    {
        return match ($option->type) {
            EventType::Pass => $this->resolvePass($option, $ballZone, $actor, $defense, $rng, $attackBias),
            EventType::Dribble => $this->resolveDribble($option, $ballZone, $actor, $defense, $rng, $attackBias),
            EventType::Cross => $this->resolveCross($option, $ballZone, $actor, $defense, $rng, $attackBias),
            EventType::Shot, EventType::Header => $this->resolveShot($option->type, $ballZone, $actor, $defense, $rng, $attackBias),
            default => throw new \LogicException('Unresolvable option type: '.$option->type->value),
        };
    }

    private function resolvePass(Option $option, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias): ResolveOutcome
    {
        $difficulty = max(0, $option->resultZone->x - $ballZone->x) * 0.08;
        $skill = $actor->passing / self::SKILL_SCALE * $attackBias;
        $pressure = $ballZone->threat() * self::PRESSURE_WEIGHT + $defense->pressureBonus($ballZone);
        $threshold = $this->clamp($skill - $pressure - $difficulty + $defense->paceContest($actor->pace, $ballZone));
        $draw = $rng->next();
        $success = $draw <= $threshold;

        return new ResolveOutcome(
            new Roll($difficulty, $skill, $pressure, $threshold, $draw),
            $success,
            ! $success,
            $success ? $option->resultZone : null,
            $success ? $option->targetPlayerId : null,
            false,
            false,
            $success ? null : ($ballZone->x >= 4 ? EventType::Clearance : EventType::Interception),
        );
    }

    private function resolveDribble(Option $option, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias): ResolveOutcome
    {
        $difficulty = 0.05;
        $skill = $actor->dribbling / self::SKILL_SCALE * $attackBias;
        $pressure = $ballZone->threat() * self::PRESSURE_WEIGHT + $defense->pressureBonus($ballZone);
        $threshold = $this->clamp($skill - $pressure - $difficulty + $defense->paceContest($actor->pace, $ballZone));
        $draw = $rng->next();
        $success = $draw <= $threshold;

        // A single draw decides whether a lost ball in the final third was a foul
        // won (a free-kick) rather than a clean tackle, so the two never disagree.
        $fouled = ! $success && $ballZone->x >= 3 && $rng->next() < 0.2;
        $turnover = match (true) {
            $success, $fouled => null,
            $ballZone->x >= 4 => EventType::Clearance,
            default => EventType::Tackle,
        };

        return new ResolveOutcome(
            new Roll($difficulty, $skill, $pressure, $threshold, $draw),
            $success,
            ! $success,
            $success ? $option->resultZone : null,
            null,
            false,
            false,
            $turnover,
            $fouled ? EventType::Foul : null,
        );
    }

    private function resolveCross(Option $option, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias): ResolveOutcome
    {
        $difficulty = 0.15;
        $skill = $actor->passing / self::SKILL_SCALE * $attackBias;
        $pressure = $ballZone->threat() * self::PRESSURE_WEIGHT + $defense->pressureBonus($ballZone);
        $threshold = $this->clamp($skill - $pressure - $difficulty);
        $draw = $rng->next();
        $success = $draw <= $threshold;

        // A cleared cross often loops out for a corner; the striker meets a good one.
        return new ResolveOutcome(
            new Roll($difficulty, $skill, $pressure, $threshold, $draw),
            $success,
            ! $success,
            $success ? $option->resultZone : null,
            $success ? $option->targetPlayerId : null,
            $success,
            false,
            $success ? null : EventType::Clearance,
            (! $success && $rng->next() < 0.4) ? EventType::Corner : null,
            $success,
        );
    }

    private function resolveShot(EventType $type, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias): ResolveOutcome
    {
        // Headers are harder to convert than a shot off the deck.
        $accuracy = $type === EventType::Header ? self::HEADER_ACCURACY : self::SHOT_ACCURACY;
        $skill = $actor->finishing / self::SKILL_SCALE * $attackBias;
        $threshold = $this->clamp($skill * $accuracy - $defense->shotSuppression($ballZone), 0.05, 0.75);
        $draw = $rng->next();
        $goal = $draw <= $threshold;

        // Draw the block/corner splits unconditionally so a shot always consumes the
        // same number of rng values: the keeper changes whether it is a goal, never
        // how many chances the stream goes on to produce.
        $stopDraw = $rng->next();
        $cornerDraw = $rng->next();
        $turnover = $goal ? null : ($stopDraw < 0.35 ? EventType::Block : EventType::Save);
        $deadBall = (! $goal && $cornerDraw < 0.3) ? EventType::Corner : null;

        return new ResolveOutcome(
            new Roll(0.0, $skill, 0.0, $threshold, $draw),
            $goal,
            true,
            null,
            null,
            true,
            $goal,
            $turnover,
            $deadBall,
        );
    }

    private function clamp(float $value, float $min = 0.05, float $max = 0.95): float
    {
        return max($min, min($max, $value));
    }
}
