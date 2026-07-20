<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\Scout;
use App\Models\Season;
use App\Sim\Domain\Position;
use Carbon\CarbonImmutable;

/**
 * Each week, any scout whose delivery date has arrived brings in 1-3 raw youth
 * prospects (12-18, still well short of their ceiling), then lines up the next
 * batch 2-4 weeks out. A better scout unearths higher-potential talent.
 */
class DeliverProspects
{
    public function handle(Season $season): void
    {
        $current = CarbonImmutable::parse($season->current_date);

        $scouts = Scout::query()
            ->where('user_id', $season->user_id)
            ->scouting()
            ->whereNotNull('next_delivery_on')
            ->whereDate('next_delivery_on', '<=', $current)
            ->get();

        foreach ($scouts as $scout) {
            foreach (range(1, random_int(1, 3)) as $ignored) {
                $this->mintProspect($scout);
            }

            $scout->forceFill(['next_delivery_on' => $current->addWeeks(random_int(2, 4))])->save();
        }
    }

    private function mintProspect(Scout $scout): void
    {
        $floor = 3 + $scout->rating;
        $potential = min(20, 8 + $scout->rating * 2 + random_int(-1, 2));

        Player::create([
            'user_id' => $scout->user_id,
            'name' => fake()->name(),
            'position' => fake()->randomElement([Position::Defender, Position::Midfielder, Position::Forward]),
            'age' => random_int(12, 18),
            'potential' => $potential,
            'is_youth' => true,
            'vision' => random_int(3, $floor),
            'passing' => random_int(3, $floor),
            'dribbling' => random_int(3, $floor),
            'finishing' => random_int(3, $floor),
            'tackling' => random_int(3, $floor),
            'pace' => random_int(4, $floor + 1),
        ]);
    }
}
