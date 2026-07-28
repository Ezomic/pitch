<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\ShapeHarness;
use App\Sim\Analysis\ShapeResult;
use Illuminate\Console\Command;

class AnalyzeShape extends Command
{
    protected $signature = 'pitch:shape {--seeds=100 : Matches to simulate} {--rating=72 : Even rating for both sides}';

    protected $description = 'Average player positions across a batch and show each side\'s shape';

    private const WIDTH = 46;

    private const HEIGHT = 15;

    public function handle(ShapeHarness $harness): int
    {
        $seeds = max(1, (int) $this->option('seeds'));
        $rating = (int) $this->option('rating');

        $this->info("Averaging positions over {$seeds} matches (rating {$rating})...");
        $shape = $harness->run($seeds, $rating);

        foreach ([0 => 'Home (attacks →)', 1 => 'Away (attacks ←)'] as $side => $label) {
            $this->newLine();
            $this->line("<info>{$label}</info>  G=keeper, 1-9=outfield, 0=slot 10");

            foreach ($this->pitch($shape, $side) as $row) {
                $this->line($row);
            }

            $m = $shape->shape($side);
            $this->line(sprintf(
                'line height %.2f · length %.2f · width %.2f · compactness %.3f',
                $m['lineHeight'], $m['length'], $m['width'], $m['compactness'],
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function pitch(ShapeResult $shape, int $side): array
    {
        $grid = array_fill(0, self::HEIGHT, array_fill(0, self::WIDTH, ' '));
        $mid = intdiv(self::WIDTH, 2);

        for ($r = 0; $r < self::HEIGHT; $r++) {
            $grid[$r][$mid] = ':';
        }

        foreach ($shape->side($side) as $slot => [$x, $y]) {
            $col = (int) round($x * (self::WIDTH - 1));
            $row = (int) round($y * (self::HEIGHT - 1));
            $grid[$row][$col] = $slot === 0 ? 'G' : (string) ($slot % 10);
        }

        $border = '+'.str_repeat('-', self::WIDTH).'+';
        $rows = [$border];
        foreach ($grid as $line) {
            $rows[] = '|'.implode('', $line).'|';
        }
        $rows[] = $border;

        return $rows;
    }
}
