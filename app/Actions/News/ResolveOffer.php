<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;
use App\Models\Player;
use App\Models\Squad;
use App\Models\SquadPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settle a standing transfer offer from the news feed. Accepting banks the bid
 * and sends the player to the buyer; declining simply files the item away.
 */
class ResolveOffer
{
    /**
     * @throws ValidationException
     */
    public function handle(News $news, Squad $squad, bool $accept): void
    {
        if ($news->user_id !== $squad->user_id || $news->category !== News::OFFER || $news->resolved_at !== null) {
            throw ValidationException::withMessages(['offer' => 'That offer is no longer open.']);
        }

        DB::transaction(function () use ($news, $squad, $accept): void {
            if ($accept) {
                $this->accept($news, $squad);
            }

            $news->forceFill(['resolved_at' => now(), 'read_at' => now()])->save();
        });
    }

    private function accept(News $news, Squad $squad): void
    {
        $payload = $news->payload ?? [];
        $player = Player::find($payload['player_id'] ?? null);
        $fee = (int) ($payload['fee'] ?? 0);

        if (! $player instanceof Player || $player->user_id !== $squad->user_id) {
            throw ValidationException::withMessages(['offer' => 'That player is no longer at the club.']);
        }

        SquadPlayer::query()->where('player_id', $player->id)->delete();
        $player->forceFill(['user_id' => null, 'is_free_agent' => true])->save();
        $squad->forceFill(['bank' => $squad->bank + $fee])->save();
    }
}
