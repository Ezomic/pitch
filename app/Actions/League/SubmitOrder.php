<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\CareerMembership;
use App\Models\LeagueOrder;
use App\Models\LeagueRound;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;

/**
 * Hand in a team sheet for a matchday.
 *
 * Orders can be changed as often as a manager likes while the round is open;
 * what counts is what stands when it plays.
 */
class SubmitOrder
{
    public function handle(LeagueRound $round, CareerMembership $seat, string $formation, string $mentality, bool $ready): ?LeagueOrder
    {
        if (! $round->isOpen() || $seat->team_id === null) {
            return null;
        }

        $existing = $round->orderFor($seat);

        $attributes = [
            'formation' => Formation::fromId($formation)->id,
            'mentality' => Mentality::fromId($mentality)->value,
            'ready' => $ready,
            'auto' => false,
        ];

        if ($existing instanceof LeagueOrder) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return $round->orders()->create([...$attributes, 'career_membership_id' => $seat->id]);
    }
}
