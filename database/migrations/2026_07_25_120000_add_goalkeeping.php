<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->unsignedTinyInteger('handling')->default(50)->after('pace');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->unsignedTinyInteger('keeping')->default(60)->after('pace');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->foreignId('goalkeeper_id')->nullable()->after('division')
                ->constrained('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goalkeeper_id');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('keeping');
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('handling');
        });
    }
};
