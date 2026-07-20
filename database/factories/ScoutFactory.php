<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScoutStatus;
use App\Models\Scout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scout>
 */
class ScoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'rating' => fake()->numberBetween(1, 5),
            'status' => ScoutStatus::Available,
            'next_delivery_on' => null,
        ];
    }

    public function idle(): static
    {
        return $this->state(fn () => ['status' => ScoutStatus::Idle]);
    }

    public function scouting(): static
    {
        return $this->state(fn () => ['status' => ScoutStatus::Scouting]);
    }
}
