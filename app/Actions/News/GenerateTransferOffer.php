<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Engine\Rng;
use Carbon\CarbonImmutable;

/**
 * Once a week a rival may table a bid for one of the club's better players. Only
 * one offer stands at a time; it lands in the news feed for the user to accept or
 * decline. Deterministic off the season and the current week.
 */
class GenerateTransferOffer
{
    private const float WEEKLY_CHANCE = 0.4;

    public function __construct(
        private readonly RecordNews $recordNews = new RecordNews,
    ) {}

    public function handle(Season $season): void
    {
        if (News::query()->where('user_id', $season->user_id)->openOffers()->exists()) {
            return;
        }

        $week = CarbonImmutable::parse($season->starts_on)->diffInWeeks(CarbonImmutable::parse($season->current_date));
        $rng = new Rng($season->id * 1000 + 800 + (int) $week);

        if ($rng->next() > self::WEEKLY_CHANCE) {
            return;
        }

        $target = Player::query()
            ->where('user_id', $season->user_id)
            ->where('is_youth', false)
            ->orderByDesc('vision')
            ->first();

        if ($target === null) {
            return;
        }

        $rivals = Team::query()->where('is_youth', false)->orderBy('id')->get();
        $bidder = $rivals->isEmpty() ? null : $rivals[(int) floor($rng->next() * $rivals->count())];
        $bidderName = $bidder instanceof Team ? $bidder->name : 'A rival club';
        $fee = (int) round($target->value() * (1.1 + $rng->next() * 0.4));

        $this->recordNews->handle(
            userId: $season->user_id,
            category: News::OFFER,
            title: 'Transfer offer for '.$target->name,
            body: $bidderName.' have bid £'.$fee.'m for '.$target->name.'.',
            seasonId: $season->id,
            payload: ['player_id' => $target->id, 'fee' => $fee],
        );
    }
}
