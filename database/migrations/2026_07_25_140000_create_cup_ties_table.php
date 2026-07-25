<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cup_ties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedTinyInteger('slot');
            // Entrants are 'user' or a team id as a string, so the user and byes
            // (a null away) are unambiguous without colliding on a nullable id.
            $table->string('home');
            $table->string('away')->nullable();
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->string('winner')->nullable();
            $table->boolean('played')->default(false);
            $table->integer('seed');
            $table->timestamps();

            $table->index(['season_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cup_ties');
    }
};
