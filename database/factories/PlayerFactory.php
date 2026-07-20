<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use App\Sim\Domain\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->randomElement([Position::Defender, Position::Midfielder, Position::Forward]),
            'vision' => fake()->numberBetween(4, 18),
            'passing' => fake()->numberBetween(6, 18),
            'dribbling' => fake()->numberBetween(6, 18),
            'finishing' => fake()->numberBetween(4, 18),
            'tackling' => fake()->numberBetween(6, 18),
            'pace' => fake()->numberBetween(6, 18),
        ];
    }

    public function highVision(): static
    {
        return $this->state(fn () => ['vision' => fake()->numberBetween(16, 20)]);
    }

    public function lowVision(): static
    {
        return $this->state(fn () => ['vision' => fake()->numberBetween(3, 6)]);
    }
}
