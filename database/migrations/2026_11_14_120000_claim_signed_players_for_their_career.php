<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before PITCH-183 a signed free agent kept career_id null, which made him
     * look like the shared seed pool to every other save. Give each one back to
     * the career whose squad fields him, or else the signer's current career.
     */
    public function up(): void
    {
        $players = DB::table('players')
            ->whereNull('career_id')
            ->whereNotNull('user_id')
            ->where('is_free_agent', false)
            ->get(['id', 'user_id']);

        foreach ($players as $player) {
            $careerId = DB::table('squad_player')
                ->join('squads', 'squads.id', '=', 'squad_player.squad_id')
                ->where('squad_player.player_id', $player->id)
                ->where('squads.user_id', $player->user_id)
                ->value('squads.career_id')
                ?? DB::table('users')->where('id', $player->user_id)->value('current_career_id');

            if ($careerId !== null) {
                DB::table('players')->where('id', $player->id)->update(['career_id' => $careerId]);
            }
        }
    }

    public function down(): void {}
};
