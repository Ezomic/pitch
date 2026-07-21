<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            // A user may now hold many seasons (one active, the rest completed).
            $table->dropUnique(['user_id']);
            $table->unsignedInteger('number')->default(1)->after('user_id');
            $table->timestamp('completed_at')->nullable()->after('current_date');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropColumn(['number', 'completed_at']);
            $table->unique('user_id');
        });
    }
};
