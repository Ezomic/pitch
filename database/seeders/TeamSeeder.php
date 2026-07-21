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

        foreach ($this->youthTeams() as $youth) {
            Team::create($youth);
        }
    }

    /**
     * Weaker academy sides for the youth league.
     *
     * @return list<array<string, mixed>>
     */
    private function youthTeams(): array
    {
        return [
            ['name' => 'Old Harbour Youth', 'style' => 'Balanced', 'is_youth' => true, 'formation' => '442', 'mentality' => 'balanced', 'vision' => 40, 'passing' => 40, 'dribbling' => 40, 'finishing' => 40, 'tackling' => 40, 'pace' => 45],
            ['name' => 'Tiki Rovers Youth', 'style' => 'Possession', 'is_youth' => true, 'formation' => '433', 'mentality' => 'attacking', 'vision' => 45, 'passing' => 45, 'dribbling' => 35, 'finishing' => 30, 'tackling' => 30, 'pace' => 35],
            ['name' => 'Blaze United Youth', 'style' => 'Attacking', 'is_youth' => true, 'formation' => '343', 'mentality' => 'attacking', 'vision' => 30, 'passing' => 30, 'dribbling' => 45, 'finishing' => 45, 'tackling' => 25, 'pace' => 45],
            ['name' => 'Ferrous Wall Youth', 'style' => 'Defensive', 'is_youth' => true, 'formation' => '532', 'mentality' => 'defensive', 'vision' => 30, 'passing' => 30, 'dribbling' => 25, 'finishing' => 25, 'tackling' => 50, 'pace' => 45],
            ['name' => 'Central Standard Youth', 'style' => 'Balanced', 'is_youth' => true, 'formation' => '4231', 'mentality' => 'balanced', 'vision' => 35, 'passing' => 35, 'dribbling' => 35, 'finishing' => 35, 'tackling' => 35, 'pace' => 35],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rivals(): array
    {
        return [
            // name, style, formation, mentality, vision, passing, dribbling, finishing, tackling, pace
            ['name' => 'Old Harbour', 'style' => 'Balanced', 'is_derby' => true, 'formation' => '442', 'mentality' => 'balanced', 'vision' => 75, 'passing' => 75, 'dribbling' => 70, 'finishing' => 70, 'tackling' => 75, 'pace' => 70],
            ['name' => 'Tiki Rovers', 'style' => 'Possession', 'formation' => '433', 'mentality' => 'attacking', 'vision' => 85, 'passing' => 80, 'dribbling' => 65, 'finishing' => 55, 'tackling' => 50, 'pace' => 50],
            ['name' => 'Blaze United', 'style' => 'Attacking', 'formation' => '343', 'mentality' => 'attacking', 'vision' => 60, 'passing' => 60, 'dribbling' => 75, 'finishing' => 80, 'tackling' => 40, 'pace' => 70],
            ['name' => 'Ferrous Wall', 'style' => 'Defensive', 'formation' => '532', 'mentality' => 'defensive', 'vision' => 50, 'passing' => 55, 'dribbling' => 45, 'finishing' => 45, 'tackling' => 85, 'pace' => 75],
            ['name' => 'Central Standard', 'style' => 'Balanced', 'formation' => '4231', 'mentality' => 'balanced', 'vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60],
            ['name' => 'Marsh End Athletic', 'style' => 'Attacking', 'formation' => '352', 'mentality' => 'balanced', 'vision' => 45, 'passing' => 45, 'dribbling' => 55, 'finishing' => 65, 'tackling' => 45, 'pace' => 60],
            ['name' => 'Loamshire Town', 'style' => 'Balanced', 'formation' => '532', 'mentality' => 'defensive', 'vision' => 40, 'passing' => 40, 'dribbling' => 40, 'finishing' => 40, 'tackling' => 40, 'pace' => 40],
        ];
    }
}
