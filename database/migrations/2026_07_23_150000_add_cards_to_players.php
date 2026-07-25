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
            $table->unsignedInteger('yellow_cards')->default(0)->after('injured_weeks');
            $table->unsignedInteger('suspended_weeks')->default(0)->after('yellow_cards');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['yellow_cards', 'suspended_weeks']);
        });
    }
};
