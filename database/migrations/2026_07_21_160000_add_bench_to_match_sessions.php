<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_sessions', function (Blueprint $table) {
            $table->json('lineup')->nullable()->after('away_goals');
            $table->json('bench')->nullable()->after('lineup');
            $table->unsignedTinyInteger('subs_remaining')->default(3)->after('bench');
        });
    }

    public function down(): void
    {
        Schema::table('match_sessions', function (Blueprint $table) {
            $table->dropColumn(['lineup', 'bench', 'subs_remaining']);
        });
    }
};
