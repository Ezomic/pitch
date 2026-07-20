<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class MatchEvent
{
    public function __construct(
        public int $minute,
        public EventType $type,
        public int $actorId,
        public ?int $targetId,
        public Zone $from,
        public ?Zone $to,
        public bool $success,
        public ?Decision $decision,
        public ?Roll $roll,
    ) {}

    public function isProgressivePass(): bool
    {
        return $this->type === EventType::Pass
            && $this->success
            && $this->to !== null
            && $this->to->x > $this->from->x;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'minute' => $this->minute,
            'type' => $this->type->value,
            'actor_id' => $this->actorId,
            'target_id' => $this->targetId,
            'from' => [$this->from->x, $this->from->y],
            'to' => $this->to !== null ? [$this->to->x, $this->to->y] : null,
            'success' => $this->success,
            'decision' => $this->decision !== null ? [
                'options_visible' => $this->decision->optionsVisible,
                'options_total' => $this->decision->optionsTotal,
                'chosen_threat' => $this->decision->chosenThreat,
                'best_available_threat' => $this->decision->bestAvailableThreat,
            ] : null,
            'roll' => $this->roll !== null ? [
                'base_difficulty' => $this->roll->baseDifficulty,
                'attribute_contribution' => $this->roll->attributeContribution,
                'pressure' => $this->roll->pressure,
                'threshold' => $this->roll->threshold,
                'draw' => $this->roll->draw,
            ] : null,
        ];
    }
}
