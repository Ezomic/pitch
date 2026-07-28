<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\ExperimentHarness;
use App\Sim\Analysis\ExperimentReport;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RunExperiment extends Command
{
    protected $signature = 'pitch:experiment {attribute : vision|passing|dribbling|finishing|tackling|pace} {--delta=15 : Amount to raise it by} {--seeds=200 : Matches per arm} {--rating=72 : Even baseline rating}';

    protected $description = 'Raise one attribute on the home side and measure the effect over a batch';

    public function handle(ExperimentHarness $harness): int
    {
        $attribute = (string) $this->argument('attribute');
        $delta = (int) $this->option('delta');
        $seeds = max(1, (int) $this->option('seeds'));
        $rating = (int) $this->option('rating');

        $this->info("Home {$attribute} {$rating} → ".($rating + $delta)." over {$seeds} matches each way...");

        try {
            $result = $harness->run($attribute, $delta, $seeds, $rating);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $report = new ExperimentReport($result['control'], $result['treatment']);

        $rows = array_map(fn (array $r): array => [
            $r['label'],
            number_format($r['control'], 2).$r['unit'],
            number_format($r['treatment'], 2).$r['unit'],
            $this->change($r['delta'], $r['unit']),
        ], $report->rows());

        $this->table(['Metric', 'Even', "+{$delta} {$attribute}", 'Change'], $rows);

        return self::SUCCESS;
    }

    private function change(float $delta, string $unit): string
    {
        $sign = $delta > 0 ? '+' : '';
        $tag = $delta > 0 ? 'info' : ($delta < 0 ? 'comment' : 'line');

        return "<{$tag}>{$sign}".number_format($delta, 2).$unit."</{$tag}>";
    }
}
