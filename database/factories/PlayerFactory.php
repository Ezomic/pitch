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
            'age' => fake()->numberBetween(18, 33),
            'potential' => fake()->numberBetween(10, 18),
            'is_youth' => false,
            'vision' => fake()->numberBetween(4, 18),
            'passing' => fake()->numberBetween(6, 18),
            'dribbling' => fake()->numberBetween(6, 18),
            'finishing' => fake()->numberBetween(4, 18),
            'tackling' => fake()->numberBetween(6, 18),
            'pace' => fake()->numberBetween(6, 18),
        ];
    }

    /**
     * A raw academy prospect: young, owned, well short of a high ceiling.
     */
    public function youth(?int $userId = null): static
    {
        return $this->state(fn () => [
            'user_id' => $userId,
            'is_youth' => true,
            'age' => fake()->numberBetween(12, 18),
            'potential' => fake()->numberBetween(14, 20),
            'vision' => fake()->numberBetween(3, 9),
            'passing' => fake()->numberBetween(4, 9),
            'dribbling' => fake()->numberBetween(4, 9),
            'finishing' => fake()->numberBetween(3, 9),
            'tackling' => fake()->numberBetween(4, 9),
            'pace' => fake()->numberBetween(5, 10),
        ]);
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
