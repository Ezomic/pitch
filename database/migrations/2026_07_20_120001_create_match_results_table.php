<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')->constrained()->cascadeOnDelete();
            $table->string('arm');
            $table->unsignedInteger('seed');
            $table->unsignedInteger('home_score');
            $table->unsignedInteger('away_score');
            $table->unsignedInteger('shots');
            $table->unsignedInteger('chances');
            $table->unsignedInteger('passes_completed');
            $table->unsignedInteger('progressive_passes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }
};
