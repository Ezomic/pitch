<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sim\Analysis\GoldenMaster;
use Illuminate\Console\Command;

class GoldenMasterCommand extends Command
{
    protected $signature = 'pitch:golden-master {--write : Re-record the fixture from the engines as they are now}';

    protected $description = 'Check the engines still produce the recorded output, or deliberately re-record it';

    public function handle(GoldenMaster $master): int
    {
        $this->info('Simulating '.GoldenMaster::SEEDS.' seeds across every engine path...');
        $current = $master->digests();

        if ($this->option('write')) {
            $diverged = $master->diverged($current, $master->recorded());
            $master->record($current);

            $this->info('Recorded '.count($current).' digests to '.GoldenMaster::path().'.');
            $this->line($diverged === []
                ? 'Nothing moved: the fixture was already current.'
                : count($diverged).' entries moved: '.implode(', ', $diverged));

            return self::SUCCESS;
        }

        $diverged = $master->diverged($current, $master->recorded());

        if ($diverged === []) {
            $this->info('All '.count($current).' digests match the recorded fixture.');

            return self::SUCCESS;
        }

        $this->error(count($diverged).' of '.count($current).' digests no longer match:');
        foreach ($diverged as $key) {
            $this->line("  {$key}");
        }
        $this->newLine();
        $this->comment('If this change was intended, re-record with: php artisan pitch:golden-master --write');

        return self::FAILURE;
    }
}
