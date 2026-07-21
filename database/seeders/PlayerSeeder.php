<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Player;
use App\Sim\Domain\Position;
use Illuminate\Database\Seeder;

class PlayerSeeder extends Seeder
{
    public function run(): void
    {
        if (Player::query()->exists()) {
            return;
        }

        mt_srand(1);

        foreach ($this->roster() as [$name, $position]) {
            $attributes = [
                'vision' => mt_rand(20, 90),
                'passing' => mt_rand(30, 90),
                'dribbling' => mt_rand(30, 90),
                'finishing' => mt_rand(20, 90),
                'tackling' => mt_rand(30, 90),
                'pace' => mt_rand(30, 90),
            ];

            $overall = (int) round(array_sum($attributes) / count($attributes));

            Player::create([
                ...$attributes,
                'name' => $name,
                'position' => $position,
                'age' => mt_rand(18, 33),
                'potential' => min(100, $overall + mt_rand(0, 15)),
                'is_youth' => false,
            ]);
        }

        mt_srand();
    }

    /**
     * @return list<array{string, Position}>
     */
    private function roster(): array
    {
        $defenders = ['Koen Bakker', 'Sven de Wit', 'Bram Visser', 'Daan Mulder', 'Ruben Smit', 'Tim Jansen', 'Lars Bos', 'Joris Peters'];
        $midfielders = ['Milan Vos', 'Sem Dijkstra', 'Finn Hendriks', 'Luuk Groen', 'Noud van Dijk', 'Gijs Willems', 'Teun Kok', 'Stijn Meijer', 'Cas Bakels', 'Roan Vermeer', 'Jesse Kuiper', 'Thijs Post'];
        $forwards = ['Youri Blom', 'Kai Verhoeven', 'Mees Scholten', 'Dani Prins', 'Levi van Loon', 'Nout Hofman', 'Guus Timmer', 'Ryan de Boer', 'Jayden Maas', 'Sam Koster'];

        $roster = [];
        foreach ($defenders as $name) {
            $roster[] = [$name, Position::Defender];
        }
        foreach ($midfielders as $name) {
            $roster[] = [$name, Position::Midfielder];
        }
        foreach ($forwards as $name) {
            $roster[] = [$name, Position::Forward];
        }

        return $roster;
    }
}
