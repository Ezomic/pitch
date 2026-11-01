<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table): void {
            $table->string('engine')->default('zone')->after('round_hours');
        });
    }

    public function down(): void
    {
        Schema::table('careers', function (Blueprint $table): void {
            $table->dropColumn('engine');
        });
    }
};
