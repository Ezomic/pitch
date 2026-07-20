<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('seed');
            $table->unsignedInteger('matches');
            $table->unsignedTinyInteger('low_vision');
            $table->unsignedTinyInteger('high_vision');
            $table->boolean('separated');
            $table->json('report');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_runs');
    }
};
