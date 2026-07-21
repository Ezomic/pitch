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
            'potential' => fake()->numberBetween(50, 90),
            'is_youth' => false,
            'vision' => fake()->numberBetween(20, 90),
            'passing' => fake()->numberBetween(30, 90),
            'dribbling' => fake()->numberBetween(30, 90),
            'finishing' => fake()->numberBetween(20, 90),
            'tackling' => fake()->numberBetween(30, 90),
            'pace' => fake()->numberBetween(30, 90),
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
            'potential' => fake()->numberBetween(70, 100),
            'vision' => fake()->numberBetween(15, 45),
            'passing' => fake()->numberBetween(20, 45),
            'dribbling' => fake()->numberBetween(20, 45),
            'finishing' => fake()->numberBetween(15, 45),
            'tackling' => fake()->numberBetween(20, 45),
            'pace' => fake()->numberBetween(25, 50),
        ]);
    }

    public function highVision(): static
    {
        return $this->state(fn () => ['vision' => fake()->numberBetween(80, 100)]);
    }

    public function lowVision(): static
    {
        return $this->state(fn () => ['vision' => fake()->numberBetween(15, 30)]);
    }
}
