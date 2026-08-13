<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manager had exactly one squad, enforced by a unique index on user_id.
     * That is the schema saying a user has one save, so it has to move: a squad
     * is one per career, and a manager may hold several.
     */
    public function up(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->dropUnique('squads_user_id_unique');
            $table->unique('career_id');
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->dropUnique(['career_id']);
            $table->unique('user_id');
        });
    }
};
