<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Career;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Career>
 */
class CareerFactory extends Factory
{
    protected $model = Career::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'My career',
            'type' => Career::SOLO,
            'status' => 'active',
            'last_played_at' => now(),
        ];
    }

    public function league(): static
    {
        return $this->state(fn (): array => ['type' => Career::LEAGUE]);
    }
}
