<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'style' => fake()->randomElement(['Balanced', 'Defensive', 'Attacking']),
            'formation' => fake()->randomElement(['balanced', 'defensive', 'attacking']),
            'mentality' => fake()->randomElement(['balanced', 'defensive', 'attacking']),
            'vision' => fake()->numberBetween(6, 16),
            'passing' => fake()->numberBetween(6, 16),
            'dribbling' => fake()->numberBetween(6, 16),
            'finishing' => fake()->numberBetween(6, 16),
            'tackling' => fake()->numberBetween(6, 16),
            'pace' => fake()->numberBetween(6, 16),
        ];
    }
}
