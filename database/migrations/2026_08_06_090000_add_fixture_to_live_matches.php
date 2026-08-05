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
            // The league fixture this match is playing out. Nullable: a match can
            // still be a friendly against a random club, which counts for nothing.
            $table->foreignId('fixture_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();

            // Who scored, slot by slot, accumulated as the match runs. Needed at
            // full time to credit goals and settle each player's form.
            $table->json('scorers')->nullable()->after('moments');
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixture_id');
            $table->dropColumn('scorers');
        });
    }
};
