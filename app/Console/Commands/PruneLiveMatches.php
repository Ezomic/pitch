<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LiveMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class PruneLiveMatches extends Command
{
    protected $signature = 'pitch:prune-live-matches {--days=7 : Keep matches touched within this many days}';

    protected $description = 'Clear out live matches that are finished, abandoned or long since left alone';

    /**
     * Each row carries a whole serialised engine state, so they are worth
     * clearing out rather than keeping forever. An abandoned match goes at once:
     * the manager walked away from it by kicking off another. Finished and
     * in-progress matches wait out the window, so someone who steps away
     * mid-match can still come back to it.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Date::now()->subDays($days);

        $abandoned = LiveMatch::query()->where('status', LiveMatch::ABANDONED)->delete();

        $stale = LiveMatch::query()
            ->whereIn('status', [LiveMatch::FINISHED, LiveMatch::LIVE])
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$abandoned} abandoned and {$stale} stale live matches (older than {$days} days).");

        return self::SUCCESS;
    }
}
