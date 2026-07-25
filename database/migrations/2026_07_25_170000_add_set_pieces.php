<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->unsignedTinyInteger('set_pieces')->default(55)->after('keeping');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->foreignId('set_piece_taker_id')->nullable()->after('goalkeeper_id')
                ->constrained('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('set_piece_taker_id');
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('set_pieces');
        });
    }
};
