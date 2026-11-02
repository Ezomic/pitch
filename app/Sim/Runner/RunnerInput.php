<?php

declare(strict_types=1);

namespace App\Sim\Runner;

use App\Sim\Squad\TeamSetup;

final readonly class RunnerInput
{
    public const int SCHEMA = 1;

    public function __construct(
        public TeamSetup $home,
        public TeamSetup $away,
        public int $seed,
    ) {}

    /**
     * @return array{schema: int, seed: int, home: array<string, mixed>, away: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'seed' => $this->seed,
            'home' => self::side($this->home, true),
            'away' => self::side($this->away, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function side(TeamSetup $setup, bool $home): array
    {
        $players = [];
        foreach ($setup->bySlot as $slot => $attributes) {
            $players[] = [
                'slot' => $slot,
                'vision' => $attributes->vision,
                'passing' => $attributes->passing,
                'dribbling' => $attributes->dribbling,
                'finishing' => $attributes->finishing,
                'tackling' => $attributes->tackling,
                'pace' => $attributes->pace,
                'fitness' => $setup->fitness[$slot] ?? 100,
            ];
        }

        return [
            'home' => $home,
            'formation' => $setup->formation->id,
            'mentality' => $setup->mentality->value,
            'goalkeeping' => $setup->goalkeeping,
            'set_piece' => $setup->setPiece,
            'players' => $players,
        ];
    }
}
