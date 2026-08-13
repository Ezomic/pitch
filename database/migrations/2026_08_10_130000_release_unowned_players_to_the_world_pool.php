<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the unclaimed player pool back to the world.
     *
     * PITCH-132's backfill handed every untagged row to the single career that
     * existed, the pool included. That was harmless while a manager could only
     * have one save, but PITCH-133 reads career_id and PITCH-134 lets a second
     * save be started: with the pool claimed by the first, the second would
     * open with no players to pick at all.
     *
     * A player nobody owns belongs to the world, not to a save. Anyone actually
     * signed, or brought through an academy, has a user and keeps their career.
     */
    public function up(): void
    {
        DB::table('players')->whereNull('user_id')->update(['career_id' => null]);
    }

    public function down(): void
    {
        // The claim cannot be put back without knowing which save it came from,
        // and an unclaimed pool is the correct state regardless.
    }
};
