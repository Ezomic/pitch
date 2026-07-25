<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squad_player', function (Blueprint $table): void {
            $table->string('role')->nullable()->after('slot');
        });
    }

    public function down(): void
    {
        Schema::table('squad_player', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
