<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The club this manager runs in that league. Null until they pick one;
            // every club nobody has claimed is played by the AI.
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            // A manager holds one seat in a league, and a club has one manager.
            $table->unique(['career_id', 'user_id']);
            $table->unique(['career_id', 'team_id']);
        });

        Schema::create('career_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();

            // Kept rather than deleted once used, so a league can show who came in
            // on which invitation.
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_invitations');
        Schema::dropIfExists('career_memberships');
    }
};
