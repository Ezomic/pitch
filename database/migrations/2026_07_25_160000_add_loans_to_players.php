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
            $table->boolean('on_loan')->default(false)->after('contract_years');
            $table->unsignedInteger('loan_weeks_remaining')->default(0)->after('on_loan');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['on_loan', 'loan_weeks_remaining']);
        });
    }
};
