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
            $table->unsignedInteger('contract_years')->default(3)->after('suspended_weeks');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->integer('weekly_income')->default(20)->after('bank');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('contract_years');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->dropColumn('weekly_income');
        });
    }
};
