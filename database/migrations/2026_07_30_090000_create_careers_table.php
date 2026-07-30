<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The game tables whose rows belong to one career. */
    private const SCOPED = [
        'squads',
        'seasons',
        'players',
        'scouts',
        'match_sessions',
        'news',
        'live_matches',
    ];

    public function up(): void
    {
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // 'solo' plays against NPC clubs; 'league' is shared with other managers.
            $table->string('type')->default('solo');
            $table->string('status')->default('active');
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Additive only: nothing reads career_id yet, so the running game is
        // untouched until the queries are re-rooted.
        foreach (self::SCOPED as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('career_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        foreach (self::SCOPED as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('career_id');
            });
        }

        Schema::dropIfExists('careers');
    }

    /**
     * Give every existing player one career holding the game they already have, so
     * nobody loses a save when careers become the unit of play.
     */
    private function backfill(): void
    {
        $now = now();
        $careerIds = [];

        foreach (DB::table('users')->pluck('id') as $userId) {
            $careerId = DB::table('careers')->insertGetId([
                'user_id' => $userId,
                'name' => 'My career',
                'type' => 'solo',
                'status' => 'active',
                'last_played_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $careerIds[] = $careerId;

            foreach (self::SCOPED as $name) {
                if (! Schema::hasTable($name)) {
                    continue;
                }

                DB::table($name)->where('user_id', $userId)->update(['career_id' => $careerId]);
            }
        }

        // Some game rows are not tagged with a user at all: the player pool in
        // particular is global to the single world it was generated for (squads
        // reference players by id, and players.user_id is unused). With exactly one
        // career those rows unambiguously belong to it, so claim them rather than
        // leaving the save half-migrated. With several careers the owner cannot be
        // inferred, so they are left alone for a deliberate migration.
        if (count($careerIds) !== 1) {
            return;
        }

        foreach (self::SCOPED as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            DB::table($name)->whereNull('career_id')->update(['career_id' => $careerIds[0]]);
        }
    }
};
