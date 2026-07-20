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
            Player::create([
                'name' => $name,
                'position' => $position,
                'vision' => mt_rand(4, 18),
                'passing' => mt_rand(6, 18),
                'dribbling' => mt_rand(6, 18),
                'finishing' => mt_rand(4, 18),
                'tackling' => mt_rand(6, 18),
                'pace' => mt_rand(6, 18),
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
