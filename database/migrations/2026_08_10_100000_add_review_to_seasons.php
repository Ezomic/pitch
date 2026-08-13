<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // What each of the manager's players did across the campaign, added to
            // as every fixture finishes. It cannot be worked out at the end: live
            // matches are pruned after a week, so by the time a season closes its
            // early matches are long gone.
            $table->json('review')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('review');
        });
    }
};
