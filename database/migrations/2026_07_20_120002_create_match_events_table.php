<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('simulation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_result_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minute');
            $table->string('type');
            $table->unsignedInteger('actor_id');
            $table->unsignedInteger('target_id')->nullable();
            $table->boolean('success');
            $table->json('decision')->nullable();
            $table->json('roll')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
