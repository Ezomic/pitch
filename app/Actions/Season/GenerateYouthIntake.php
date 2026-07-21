<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\User;
use App\Sim\Domain\Position;

/**
 * A fresh crop of academy prospects arrives each preseason, independent of
 * scouting, so the youth pipeline keeps flowing year on year.
 */
class GenerateYouthIntake
{
    private const int COUNT = 3;

    public function handle(User $user): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            Player::create([
                'user_id' => $user->id,
                'name' => fake()->name(),
                'position' => fake()->randomElement([Position::Defender, Position::Midfielder, Position::Forward]),
                'age' => random_int(15, 17),
                'potential' => random_int(60, 95),
                'is_youth' => true,
                'vision' => random_int(15, 45),
                'passing' => random_int(20, 45),
                'dribbling' => random_int(20, 45),
                'finishing' => random_int(15, 45),
                'tackling' => random_int(20, 45),
                'pace' => random_int(25, 50),
            ]);
        }
    }
}
