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

            $traits = array_keys(Player::TRAITS);
            $trait = mt_rand(1, 10) <= 4 ? $traits[mt_rand(0, count($traits) - 1)] : null;

            Player::create([
                ...$attributes,
                'name' => $name,
                'position' => $position,
                'age' => mt_rand(18, 33),
                'potential' => min(100, $overall + mt_rand(0, 15)),
                'is_youth' => false,
                'trait' => $trait,
            ]);
        }

        $this->goalkeepers();
        $this->freeAgents();

        mt_srand();
    }

    /**
     * Goalkeepers, most in the shared pool and a couple on the market, spread
     * across a wide handling range so the shot-stopping lever is easy to feel.
     */
    private function goalkeepers(): void
    {
        $keepers = [
            ['Aaron Kessler', 78, false],
            ['Bram Otten', 64, false],
            ['Colin Vaags', 52, false],
            ['Dennis Roth', 88, true],
            ['Elias Mor', 40, true],
        ];

        foreach ($keepers as [$name, $handling, $freeAgent]) {
            Player::create([
                'is_free_agent' => $freeAgent,
                'name' => $name,
                'position' => Position::Goalkeeper,
                'age' => mt_rand(21, 33),
                'potential' => min(100, $handling + mt_rand(0, 10)),
                'is_youth' => false,
                'vision' => mt_rand(20, 40),
                'passing' => mt_rand(25, 45),
                'dribbling' => mt_rand(15, 30),
                'finishing' => mt_rand(10, 20),
                'tackling' => mt_rand(20, 40),
                'pace' => mt_rand(30, 50),
                'handling' => $handling,
            ]);
        }
    }

    /** A market of unattached players the user can sign with bank money. */
    private function freeAgents(): void
    {
        $names = ['Marco Fabbri', 'Diego Souza', 'Yannick Bauer', 'Owen Clarke', 'Rafael Costa', 'Nils Berg', 'Tomas Novak', 'Ade Balogun', 'Pierre Laurent', 'Emil Larsen', 'Ivan Petrov', 'Hugo Martins'];
        $positions = [Position::Defender, Position::Midfielder, Position::Forward];

        foreach ($names as $index => $name) {
            $floor = 40 + mt_rand(0, 40);

            Player::create([
                'is_free_agent' => true,
                'name' => $name,
                'position' => $positions[$index % 3],
                'age' => mt_rand(19, 31),
                'potential' => min(100, $floor + mt_rand(0, 15)),
                'is_youth' => false,
                'vision' => min(99, $floor + mt_rand(-5, 12)),
                'passing' => min(99, $floor + mt_rand(-5, 12)),
                'dribbling' => min(99, $floor + mt_rand(-5, 12)),
                'finishing' => min(99, $floor + mt_rand(-5, 12)),
                'tackling' => min(99, $floor + mt_rand(-5, 12)),
                'pace' => min(99, $floor + mt_rand(-5, 12)),
            ]);
        }
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
