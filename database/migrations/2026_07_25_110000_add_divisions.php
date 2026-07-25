<?php

declare(strict_types=1);

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which tier each seeded rival plays in; the top division is the tougher one. */
    private const array DIVISIONS = [
        'Old Harbour' => 1,
        'Tiki Rovers' => 1,
        'Blaze United' => 1,
        'Ferrous Wall' => 1,
        'Central Standard' => 2,
        'Marsh End Athletic' => 2,
        'Loamshire Town' => 2,
    ];

    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->unsignedTinyInteger('division')->default(2)->after('is_derby');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('division')->default(2)->after('weekly_income');
        });

        Schema::table('seasons', function (Blueprint $table): void {
            $table->unsignedTinyInteger('division')->default(2)->after('number');
        });

        foreach (self::DIVISIONS as $name => $division) {
            Team::query()->where('name', $name)->where('is_youth', false)->update(['division' => $division]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('division');
        });

        Schema::table('squads', function (Blueprint $table): void {
            $table->dropColumn('division');
        });

        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropColumn('division');
        });
    }
};
