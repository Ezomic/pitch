<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_sessions', function (Blueprint $table): void {
            $table->json('scorers')->nullable()->after('bench');
        });
    }

    public function down(): void
    {
        Schema::table('match_sessions', function (Blueprint $table): void {
            $table->dropColumn('scorers');
        });
    }
};
