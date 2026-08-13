<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which save the manager is playing. Nullable so a user who has never
            // started one is not broken; the first request resolves it.
            $table->foreignId('current_career_id')->nullable()->after('id')->constrained('careers')->nullOnDelete();
        });

        // Everyone already holding a career is put back into their oldest one,
        // which for every existing save is the only one there is.
        foreach (DB::table('careers')->orderBy('id')->get() as $career) {
            DB::table('users')
                ->where('id', $career->user_id)
                ->whereNull('current_career_id')
                ->update(['current_career_id' => $career->id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_career_id');
        });
    }
};
