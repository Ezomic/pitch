<?php

declare(strict_types=1);

namespace App\Sim\Domain;

final readonly class Attributes
{
    public function __construct(
        public int $vision,
        public int $passing,
        public int $dribbling,
        public int $finishing,
        public int $tackling,
        public int $pace,
    ) {}

    public function withVision(int $vision): self
    {
        return new self($vision, $this->passing, $this->dribbling, $this->finishing, $this->tackling, $this->pace);
    }

    /**
     * Every attribute multiplied by a condition factor and clamped to the 1..100
     * scale, so fitness and form move a player's effective match rating without
     * ever pushing an attribute out of range.
     */
    public function scaled(float $factor): self
    {
        $scale = static fn (int $attr): int => max(1, min(100, (int) round($attr * $factor)));

        return new self(
            $scale($this->vision),
            $scale($this->passing),
            $scale($this->dribbling),
            $scale($this->finishing),
            $scale($this->tackling),
            $scale($this->pace),
        );
    }

    /** The named attribute nudged by a delta, clamped to the 1..100 scale. */
    public function plus(string $attribute, int $delta): self
    {
        $values = [
            'vision' => $this->vision,
            'passing' => $this->passing,
            'dribbling' => $this->dribbling,
            'finishing' => $this->finishing,
            'tackling' => $this->tackling,
            'pace' => $this->pace,
        ];

        $values[$attribute] = max(1, min(100, $values[$attribute] + $delta));

        return new self(...array_values($values));
    }

    /** The rounded mean of the six attributes: a player's overall ability. */
    public function overall(): int
    {
        $sum = $this->vision + $this->passing + $this->dribbling + $this->finishing + $this->tackling + $this->pace;

        return (int) round($sum / 6);
    }
}
