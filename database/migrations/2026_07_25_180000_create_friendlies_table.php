<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendlies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->foreignId('opponent_team_id')->constrained('teams')->cascadeOnDelete();
            $table->boolean('home')->default(true);
            $table->unsignedTinyInteger('user_goals')->nullable();
            $table->unsignedTinyInteger('opponent_goals')->nullable();
            $table->boolean('played')->default(false);
            $table->integer('seed');
            $table->timestamps();

            $table->index(['season_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendlies');
    }
};
