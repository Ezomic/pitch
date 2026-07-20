<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('matchday');
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->boolean('played')->default(false);
            $table->unsignedInteger('seed');
            $table->timestamps();

            $table->index(['season_id', 'matchday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
