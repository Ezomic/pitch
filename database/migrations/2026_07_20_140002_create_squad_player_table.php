<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('squad_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->timestamps();

            $table->unique(['squad_id', 'slot']);
            $table->unique(['squad_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('squad_player');
    }
};
