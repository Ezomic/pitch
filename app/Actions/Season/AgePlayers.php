<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\News\RecordNews;
use App\Models\News;
use App\Models\Player;
use App\Models\SquadPlayer;
use App\Models\User;

/**
 * A year passes between campaigns: every player the user owns ages by one, and
 * seniors past their peak start to regress, shedding a little from every
 * attribute so an ageing squad visibly needs refreshing. A senior's contract
 * also winds down a year; when it runs out they walk to the free-agent pool.
 */
class AgePlayers
{
    private const int PEAK_AGE = 30;

    public function __construct(
        private readonly RecordNews $recordNews = new RecordNews,
    ) {}

    public function handle(User $user): void
    {
        foreach (Player::query()->where('user_id', $user->id)->get() as $player) {
            $age = $player->age + 1;
            $update = ['age' => $age];

            if (! $player->is_youth && $age > self::PEAK_AGE) {
                $decline = $age >= 34 ? 2 : 1;

                foreach (Player::ATTRIBUTES as $attribute) {
                    $update[$attribute] = max(1, $player->{$attribute} - $decline);
                }
            }

            if (! $player->is_youth) {
                $update['contract_years'] = max(0, $player->contract_years - 1);

                if ($update['contract_years'] === 0) {
                    $this->release($player, $update);

                    continue;
                }
            }

            $player->forceFill($update)->save();
        }
    }

    /**
     * A senior whose contract has expired leaves for nothing: dropped from the
     * squad and returned to the market.
     *
     * @param  array<string, mixed>  $update
     */
    private function release(Player $player, array $update): void
    {
        SquadPlayer::query()->where('player_id', $player->id)->delete();

        $userId = $player->user_id;

        $player->forceFill([...$update, 'user_id' => null, 'is_free_agent' => true])->save();

        if ($userId !== null) {
            $this->recordNews->handle(
                userId: $userId,
                category: News::BOARD,
                title: $player->name.' left on a free',
                body: $player->name.'\'s contract expired and they have left the club. Renew deals sooner to keep your players.',
            );
        }
    }
}
