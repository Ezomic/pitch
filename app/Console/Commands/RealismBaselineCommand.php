<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\MatchMetrics;
use App\Sim\Analysis\RealismBaseline;
use App\Sim\Analysis\RealismHarness;
use App\Sim\Analysis\RealismReport;
use Illuminate\Console\Command;

class RealismBaselineCommand extends Command
{
    protected $signature = 'pitch:realism-baseline {--write : Record the per-seed counts from the engine as it is now}';

    protected $description = 'Show the realism band distances to record in RealismBandTest, or record the per-seed baseline';

    public function handle(RealismHarness $harness): int
    {
        $this->info('Simulating '.RealismBaseline::SEEDS.' seeds...');
        $perSeed = $harness->perSeed(RealismBaseline::SEEDS);

        $this->table(['Metric', 'Per match', 'Outside its band by'], array_map(
            fn (array $row): array => [
                $row['label'],
                number_format($row['value'], 1).$row['unit'],
                number_format(max($row['low'] - $row['value'], $row['value'] - $row['high'], 0.0), 2),
            ],
            (new RealismReport(MatchMetrics::sum($perSeed)))->rows(),
        ));

        if ($this->option('write')) {
            RealismBaseline::from($perSeed)->record();
            $this->info('Recorded '.count($perSeed).' seeds to '.RealismBaseline::path().'.');
        }

        return self::SUCCESS;
    }
}
