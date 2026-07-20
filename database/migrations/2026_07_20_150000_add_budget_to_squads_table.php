<?php

use App\Models\Squad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->unsignedInteger('budget')->default(Squad::DEFAULT_BUDGET)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('squads', function (Blueprint $table) {
            $table->dropColumn('budget');
        });
    }
};
