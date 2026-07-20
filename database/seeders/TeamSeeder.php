<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        if (Team::query()->exists()) {
            return;
        }

        foreach ($this->rivals() as $rival) {
            Team::create($rival);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rivals(): array
    {
        return [
            // name, style, formation, mentality, vision, passing, dribbling, finishing, tackling, pace
            ['name' => 'Old Harbour', 'style' => 'Balanced', 'formation' => '442', 'mentality' => 'balanced', 'vision' => 15, 'passing' => 15, 'dribbling' => 14, 'finishing' => 14, 'tackling' => 15, 'pace' => 14],
            ['name' => 'Tiki Rovers', 'style' => 'Possession', 'formation' => '433', 'mentality' => 'attacking', 'vision' => 17, 'passing' => 16, 'dribbling' => 13, 'finishing' => 11, 'tackling' => 10, 'pace' => 10],
            ['name' => 'Blaze United', 'style' => 'Attacking', 'formation' => '343', 'mentality' => 'attacking', 'vision' => 12, 'passing' => 12, 'dribbling' => 15, 'finishing' => 16, 'tackling' => 8, 'pace' => 14],
            ['name' => 'Ferrous Wall', 'style' => 'Defensive', 'formation' => '532', 'mentality' => 'defensive', 'vision' => 10, 'passing' => 11, 'dribbling' => 9, 'finishing' => 9, 'tackling' => 17, 'pace' => 15],
            ['name' => 'Central Standard', 'style' => 'Balanced', 'formation' => '4231', 'mentality' => 'balanced', 'vision' => 12, 'passing' => 12, 'dribbling' => 12, 'finishing' => 12, 'tackling' => 12, 'pace' => 12],
            ['name' => 'Marsh End Athletic', 'style' => 'Attacking', 'formation' => '352', 'mentality' => 'balanced', 'vision' => 9, 'passing' => 9, 'dribbling' => 11, 'finishing' => 13, 'tackling' => 9, 'pace' => 12],
            ['name' => 'Loamshire Town', 'style' => 'Balanced', 'formation' => '532', 'mentality' => 'defensive', 'vision' => 8, 'passing' => 8, 'dribbling' => 8, 'finishing' => 8, 'tackling' => 8, 'pace' => 8],
        ];
    }
}
