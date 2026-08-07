<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\LeagueHarness;
use App\Sim\Analysis\LeagueReport;
use Illuminate\Console\Command;

class AnalyzeLeague extends Command
{
    protected $signature = 'pitch:analyze-league {--seeds=60 : Repeats of the full round-robin}';

    protected $description = 'Resolve a batch of league fixtures and report them against a real division';

    public function handle(LeagueHarness $harness): int
    {
        $seeds = max(1, (int) $this->option('seeds'));

        $this->info("Resolving a round-robin {$seeds} times...");
        $metrics = $harness->run($seeds);
        $report = new LeagueReport($metrics);

        $this->table(
            ['Metric', 'Value', 'Real range', ''],
            array_map(fn (array $r): array => [
                $r['label'],
                number_format($r['value'], 1).$r['unit'],
                "{$r['low']} - {$r['high']}{$r['unit']}",
                $r['ok'] ? '<info>ok</info>' : '<comment>off</comment>',
            ], $report->rows()),
        );

        $this->line("Across {$metrics->matches} fixtures.");

        return self::SUCCESS;
    }
}
