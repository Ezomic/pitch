<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('user_id');
            $table->date('current_date')->nullable()->after('starts_on');
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->date('scheduled_on')->nullable()->after('matchday');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'current_date']);
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn('scheduled_on');
        });
    }
};
