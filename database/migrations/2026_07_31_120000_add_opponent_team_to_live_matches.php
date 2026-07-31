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
            // Which club is being played, so the match screen can show how strong
            // they are. Nullable: matches started before this, and the fallback
            // sparring side, have no club behind them.
            $table->foreignId('opponent_team_id')->nullable()->after('away_name')->constrained('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opponent_team_id');
        });
    }
};
