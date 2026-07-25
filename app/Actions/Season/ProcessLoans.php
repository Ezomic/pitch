<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Season;

/**
 * A week of loan spells. Every prospect out on loan takes an extra turn of
 * development on top of the academy's own, its clock ticks down, and when the
 * spell ends it returns with a lifted ceiling to grow into.
 */
class ProcessLoans
{
    public function __construct(
        private readonly DevelopPlayers $developPlayers = new DevelopPlayers,
    ) {}

    public function handle(Season $season): void
    {
        $loaned = Player::query()
            ->where('user_id', $season->user_id)
            ->where('is_youth', true)
            ->where('on_loan', true)
            ->get();

        foreach ($loaned as $player) {
            $this->developPlayers->handle([$player]);

            $remaining = max(0, $player->loan_weeks_remaining - 1);

            if ($remaining === 0) {
                $player->forceFill([
                    'on_loan' => false,
                    'loan_weeks_remaining' => 0,
                    'potential' => min(100, $player->potential + Player::LOAN_RETURN_POTENTIAL),
                ])->save();

                continue;
            }

            $player->forceFill(['loan_weeks_remaining' => $remaining])->save();
        }
    }
}
