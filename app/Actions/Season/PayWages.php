<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\News\RecordNews;
use App\Models\News;
use App\Models\Player;
use App\Models\Squad;
use Illuminate\Support\Facades\DB;

/**
 * Settle a week of finances: the club takes in its income and pays the combined
 * wages of its seniors. Overspend and the bank goes into the red, which blocks
 * new signings and, while unpaid, drains squad morale a notch a week.
 */
class PayWages
{
    public function __construct(
        private readonly RecordNews $recordNews = new RecordNews,
    ) {}

    public function handle(Squad $squad): void
    {
        $income = $squad->weekly_income;
        $wageBill = $squad->wageBill();

        DB::transaction(function () use ($squad, $income, $wageBill): void {
            $bank = $squad->bank - $wageBill + $income;
            $squad->forceFill(['bank' => $bank])->save();

            if ($bank < 0) {
                $this->applyUnrest($squad);
                $this->recordNews->handle(
                    userId: $squad->user_id,
                    category: News::BOARD,
                    title: 'Wages in the red',
                    body: 'The wage bill of £'.$wageBill.'m outran the club\'s income and the bank is £'.$bank.'m. Trim wages or the squad will grow unhappy.',
                );
            }
        });
    }

    /** Unpaid wages knock a point off every senior's form, down to the floor. */
    private function applyUnrest(Squad $squad): void
    {
        foreach ($squad->seniors()->get() as $player) {
            $player->forceFill([
                'form' => max(Player::FORM_MIN, $player->form - 1),
            ])->save();
        }
    }
}
