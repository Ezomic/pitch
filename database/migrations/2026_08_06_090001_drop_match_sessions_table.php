<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The league match is played out in the positional engine now, through
     * live_matches, so the older text-engine session has nothing left using it.
     */
    public function up(): void
    {
        Schema::dropIfExists('match_sessions');
    }

    public function down(): void
    {
        Schema::create('match_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->integer('home_goals')->default(0);
            $table->integer('away_goals')->default(0);
            $table->json('moments');
            $table->json('lineup')->nullable();
            $table->json('bench')->nullable();
            $table->json('scorers')->nullable();
            $table->integer('subs_remaining')->default(3);
            $table->string('status')->default('in_progress');
            $table->timestamps();
        });
    }
};
