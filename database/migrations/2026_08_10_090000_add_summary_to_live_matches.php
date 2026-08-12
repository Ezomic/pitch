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
            // What the match produced, tallied slice by slice as it is played.
            // The event stream and the per-tick frames are discarded as the match
            // runs, so anything worth knowing afterwards has to be kept here.
            $table->json('summary')->nullable()->after('scorers');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
