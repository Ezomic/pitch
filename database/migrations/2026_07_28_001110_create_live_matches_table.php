<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->integer('current_tick')->default(0);
            $table->integer('total_ticks');
            $table->json('pitch_state');
            $table->unsignedBigInteger('rng_state');
            $table->integer('home_goals')->default(0);
            $table->integer('away_goals')->default(0);
            $table->string('home_name');
            $table->string('away_name');
            $table->json('players');
            $table->json('moments');
            $table->integer('subs_remaining')->default(5);
            $table->string('status')->default('live');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_matches');
    }
};
