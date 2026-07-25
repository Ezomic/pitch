<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\News\GenerateTransferOffer;
use App\Models\Player;
use App\Models\Season;
use Carbon\CarbonImmutable;

/**
 * Advance the club calendar by one week. This is the single tick the whole
 * management layer hangs off: the senior matchday, scout deliveries, youth
 * development and the youth league all run on the same clock.
 */
class AdvanceWeek
{
    public function __construct(
        private readonly PlayMatchday $playMatchday = new PlayMatchday,
        private readonly DeliverProspects $deliverProspects = new DeliverProspects,
        private readonly DevelopPlayers $developPlayers = new DevelopPlayers,
        private readonly PlayYouthFixtures $playYouthFixtures = new PlayYouthFixtures,
        private readonly RecoverCondition $recoverCondition = new RecoverCondition,
        private readonly MentorYouth $mentorYouth = new MentorYouth,
        private readonly PayWages $payWages = new PayWages,
        private readonly PlayCupRound $playCupRound = new PlayCupRound,
        private readonly GenerateTransferOffer $generateTransferOffer = new GenerateTransferOffer,
    ) {}

    public function handle(Season $season): void
    {
        $season->update([
            'current_date' => CarbonImmutable::parse($season->current_date)->addWeek(),
        ]);

        $this->recoverCondition->handle($season);
        $this->playMatchday->handle($season);
        $this->playCupRound->handle($season);
        $this->deliverProspects->handle($season);

        $squad = $season->user->squad()->first();

        if ($squad !== null) {
            $this->payWages->handle($squad);
        }

        $this->developPlayers->handle(
            Player::query()->where('user_id', $season->user_id)->where('is_youth', true)->get()
        );

        $this->mentorYouth->handle($season);
        $this->playYouthFixtures->handle($season);
        $this->generateTransferOffer->handle($season);
    }
}
