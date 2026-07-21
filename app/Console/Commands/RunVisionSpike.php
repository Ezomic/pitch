<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Experiment\PairedRunner;
use App\Sim\Experiment\RunReport;
use App\Sim\Persistence\RunWriter;
use Illuminate\Console\Command;

final class RunVisionSpike extends Command
{
    protected $signature = 'pitch:vision-spike
        {--matches=3000 : Number of paired matches per arm}
        {--seed=1 : Base seed for the shared sequence}
        {--low=30 : Vision value for the low arm}
        {--high=80 : Vision value for the high arm}
        {--sample=20 : Matches per arm to persist with full event logs}
        {--no-persist : Skip writing the run to the database}';

    protected $description = 'Prove that vision drives measurably different match outcomes across paired simulations';

    public function handle(PairedRunner $runner, RunWriter $writer): int
    {
        $matches = (int) $this->option('matches');
        $seed = (int) $this->option('seed');
        $low = (int) $this->option('low');
        $high = (int) $this->option('high');
        $sample = $this->option('no-persist') ? 0 : (int) $this->option('sample');

        $this->info("Running {$matches} paired matches (seed {$seed}), low vision {$low} vs high vision {$high}...");

        $report = $runner->run($low, $high, $matches, $seed, $sample);

        $this->renderTable($report);

        if (! $this->option('no-persist')) {
            $run = $writer->write($report);
            $this->line("Persisted simulation run #{$run->id} with {$sample} sampled matches per arm.");
        }

        if ($report->separated()) {
            $this->info('Result: vision SEPARATES cleanly across all three metrics. The mechanic is legible.');

            return self::SUCCESS;
        }

        $this->warn('Result: metrics did NOT separate. The mechanic is not legible as built and needs rethinking.');

        return self::FAILURE;
    }

    private function renderTable(RunReport $report): void
    {
        $this->table(
            ['metric', "low ({$report->low->vision})", "high ({$report->high->vision})", 'delta'],
            [
                [
                    'mean decision gap',
                    $this->fmt($report->low->meanDecisionGap),
                    $this->fmt($report->high->meanDecisionGap),
                    $this->fmt(-$report->gapImprovement()),
                ],
                [
                    'progressive pass share',
                    $this->fmt($report->low->progressivePassShare),
                    $this->fmt($report->high->progressivePassShare),
                    '+'.$this->fmt($report->progressiveLift()),
                ],
                [
                    'chances per 90',
                    $this->fmt($report->low->chancesPer90),
                    $this->fmt($report->high->chancesPer90),
                    '+'.$this->fmt($report->chancesLift()),
                ],
                [
                    'goals per 90',
                    $this->fmt($report->low->goalsPer90),
                    $this->fmt($report->high->goalsPer90),
                    '+'.$this->fmt($report->high->goalsPer90 - $report->low->goalsPer90),
                ],
            ],
        );
    }

    private function fmt(float $value): string
    {
        return number_format($value, 4);
    }
}
