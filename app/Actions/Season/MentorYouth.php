<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Season;

/**
 * A star senior in the building rubs off on the kids: while the user owns a
 * high-calibre player, the academy gets an extra step of development each week,
 * so investing in experience pays off in the youth pipeline too.
 */
class MentorYouth
{
    private const int MENTOR_OVERALL = 80;

    public function __construct(
        private readonly DevelopPlayers $developPlayers = new DevelopPlayers,
    ) {}

    public function handle(Season $season): void
    {
        $seniors = Player::query()
            ->where('user_id', $season->user_id)
            ->where('is_youth', false)
            ->get();

        $hasMentor = $seniors->contains(fn (Player $player) => $player->overall() >= self::MENTOR_OVERALL);

        if (! $hasMentor) {
            return;
        }

        $this->developPlayers->handle(
            Player::query()->where('user_id', $season->user_id)->where('is_youth', true)->get()
        );
    }
}
