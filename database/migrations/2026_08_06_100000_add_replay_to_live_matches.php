<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            // The kickoff engine state, kept alongside the live one. A seed alone
            // does not reproduce a match: pitch_state is overwritten every slice,
            // so without this there is no way back to the first tick.
            $table->json('kickoff_state')->nullable()->after('pitch_state');

            // What the manager did mid-match and exactly when. Substitutions and
            // mentality changes both mutate engine state as the match runs, so a
            // replay has to reapply them on the same ticks to come out the same.
            $table->json('interventions')->nullable()->after('scorers');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn(['kickoff_state', 'interventions']);
        });
    }
};
