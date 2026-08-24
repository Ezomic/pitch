<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table): void {
            // How long a league waits on its managers before playing the round
            // without them. Only meaningful for a league save.
            $table->unsignedSmallInteger('round_hours')->default(24)->after('status');
        });

        Schema::create('league_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('matchday');
            $table->string('status')->default('open');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // A matchday is played once, whatever raced to resolve it.
            $table->unique(['career_id', 'matchday']);
        });

        Schema::create('league_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_membership_id')->constrained()->cascadeOnDelete();
            $table->string('formation');
            $table->string('mentality');
            $table->boolean('ready')->default(false);
            $table->boolean('auto')->default(false);
            $table->timestamps();

            $table->unique(['league_round_id', 'career_membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_orders');
        Schema::dropIfExists('league_rounds');

        Schema::table('careers', function (Blueprint $table): void {
            $table->dropColumn('round_hours');
        });
    }
};
