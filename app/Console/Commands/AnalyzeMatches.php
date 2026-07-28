<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\RealismHarness;
use App\Sim\Analysis\RealismReport;
use Illuminate\Console\Command;

class AnalyzeMatches extends Command
{
    protected $signature = 'pitch:analyze {--seeds=100 : Matches to simulate} {--rating=72 : Even rating for both sides}';

    protected $description = 'Simulate a batch of matches and report engine metrics against real-football ranges';

    public function handle(RealismHarness $harness): int
    {
        $seeds = max(1, (int) $this->option('seeds'));
        $rating = (int) $this->option('rating');

        $this->info("Simulating {$seeds} matches (rating {$rating})...");
        $report = new RealismReport($harness->run($seeds, $rating));

        $rows = array_map(fn (array $r): array => [
            $r['label'],
            number_format($r['value'], 1).$r['unit'],
            "{$r['low']} - {$r['high']}{$r['unit']}",
            $r['ok'] ? '<info>ok</info>' : '<comment>off</comment>',
        ], $report->rows());

        $this->table(['Metric', 'Per match', 'Real range', ''], $rows);

        return self::SUCCESS;
    }
}
