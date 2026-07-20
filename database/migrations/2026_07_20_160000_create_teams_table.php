<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('style');
            $table->unsignedTinyInteger('vision');
            $table->unsignedTinyInteger('passing');
            $table->unsignedTinyInteger('dribbling');
            $table->unsignedTinyInteger('finishing');
            $table->unsignedTinyInteger('tackling');
            $table->unsignedTinyInteger('pace');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
