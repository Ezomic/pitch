<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixtures', function (Blueprint $table): void {
            $table->json('runner_result')->nullable();
            $table->json('runner_events')->nullable();
            $table->string('runner_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fixtures', function (Blueprint $table): void {
            $table->dropColumn(['runner_result', 'runner_events', 'runner_version']);
        });
    }
};
