<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Domain\Roll;
use App\Sim\Domain\Zone;

final class Resolver
{
    private const float SKILL_SCALE = 20.0;

    private const float PRESSURE_WEIGHT = 0.30;

    public function resolve(Option $option, Zone $ballZone, Attributes $actor, Rng $rng): ResolveOutcome
    {
        return match ($option->type) {
            EventType::Pass => $this->resolvePass($option, $ballZone, $actor, $rng),
            EventType::Dribble => $this->resolveDribble($option, $ballZone, $actor, $rng),
            EventType::Shot => $this->resolveShot($ballZone, $actor, $rng),
            default => throw new \LogicException('Unresolvable option type: '.$option->type->value),
        };
    }

    private function resolvePass(Option $option, Zone $ballZone, Attributes $actor, Rng $rng): ResolveOutcome
    {
        $difficulty = max(0, $option->resultZone->x - $ballZone->x) * 0.08;
        $skill = $actor->passing / self::SKILL_SCALE;
        $pressure = $ballZone->threat() * self::PRESSURE_WEIGHT;
        $threshold = $this->clamp($skill - $pressure - $difficulty);
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
        );
    }

    private function resolveDribble(Option $option, Zone $ballZone, Attributes $actor, Rng $rng): ResolveOutcome
    {
        $difficulty = 0.05;
        $skill = $actor->dribbling / self::SKILL_SCALE;
        $pressure = $ballZone->threat() * self::PRESSURE_WEIGHT;
        $threshold = $this->clamp($skill - $pressure - $difficulty);
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
        );
    }

    private function resolveShot(Zone $ballZone, Attributes $actor, Rng $rng): ResolveOutcome
    {
        $skill = $actor->finishing / self::SKILL_SCALE;
        $threshold = $this->clamp($skill * 0.35, 0.02, 0.5);
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
