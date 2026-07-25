<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squads', function (Blueprint $table): void {
            $table->unsignedInteger('bank')->default(300)->after('budget');
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->boolean('is_free_agent')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table): void {
            $table->dropColumn('bank');
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('is_free_agent');
        });
    }
};
