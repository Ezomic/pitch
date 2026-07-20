<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->string('formation')->default('balanced')->after('budget');
            $table->string('mentality')->default('balanced')->after('formation');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('formation')->default('balanced')->after('style');
            $table->string('mentality')->default('balanced')->after('formation');
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->dropColumn(['formation', 'mentality']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['formation', 'mentality']);
        });
    }
};
