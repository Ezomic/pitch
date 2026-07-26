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

    public function resolve(Option $option, Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias = 1.0): ResolveOutcome
    {
        return match ($option->type) {
            EventType::Pass => $this->resolvePass($option, $ballZone, $actor, $defense, $rng, $attackBias),
            EventType::Dribble => $this->resolveDribble($option, $ballZone, $actor, $defense, $rng, $attackBias),
            EventType::Shot => $this->resolveShot($ballZone, $actor, $defense, $rng, $attackBias),
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
            $success ? null : $this->turnover(EventType::Pass, $ballZone),
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

        return new ResolveOutcome(
            new Roll($difficulty, $skill, $pressure, $threshold, $draw),
            $success,
            ! $success,
            $success ? $option->resultZone : null,
            null,
            false,
            false,
            $success ? null : $this->turnover(EventType::Dribble, $ballZone),
        );
    }

    /**
     * How the defence won the ball back: a clearance when the danger was in the
     * final third, otherwise an interception of a pass or a tackle on a dribble.
     */
    private function turnover(EventType $attack, Zone $ballZone): EventType
    {
        if ($ballZone->x >= 4) {
            return EventType::Clearance;
        }

        return $attack === EventType::Dribble ? EventType::Tackle : EventType::Interception;
    }

    private function resolveShot(Zone $ballZone, Attributes $actor, Defense $defense, Rng $rng, float $attackBias): ResolveOutcome
    {
        $skill = $actor->finishing / self::SKILL_SCALE * $attackBias;
        $threshold = $this->clamp($skill * 0.9 - $defense->shotSuppression($ballZone), 0.05, 0.75);
        $draw = $rng->next();
        $goal = $draw <= $threshold;

        return new ResolveOutcome(
            new Roll(0.0, $skill, 0.0, $threshold, $draw),
            $goal,
            true,
            null,
            null,
            true,
            $goal,
        );
    }

    private function clamp(float $value, float $min = 0.05, float $max = 0.95): float
    {
        return max($min, min($max, $value));
    }
}
