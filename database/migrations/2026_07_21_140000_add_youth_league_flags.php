<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_youth')->default(false)->after('style');
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->boolean('youth')->default(false)->after('matchday');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_youth');
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn('youth');
        });
    }
};
