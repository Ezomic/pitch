<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Player;
use App\Models\Squad;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;

/**
 * The "what-if" lever: for each player in the XI, how much a small bump to each
 * of their attributes would move the team's goals scored and conceded. It re-runs
 * the deterministic SquadEvaluator once per slot-and-attribute against the same
 * baseline opponent, so every number is a clean, reproducible marginal delta.
 */
class MarginalValue
{
    private const int DELTA = 5;

    private const int MATCHES = 60;

    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    /**
     * @return array{delta: int, baseline: array{goals: float, conceded: float}, rows: list<array{slot: int, name: string, attributes: array<string, array{goals: float, conceded: float}>}>}
     */
    public function handle(Squad $squad): array
    {
        $bySlot = $squad->attributesBySlot();
        $formation = Formation::fromId($squad->formation);
        $mentality = Mentality::fromId($squad->mentality);
        $opponent = TeamSetup::baseline();

        $baseline = $this->evaluator->evaluate(new TeamSetup($bySlot, $formation, $mentality), $opponent, self::MATCHES);

        $rows = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $slot = $assignment->slot;
            $attributes = [];

            foreach (Player::ATTRIBUTES as $attribute) {
                $modified = $bySlot;
                $modified[$slot] = $bySlot[$slot]->plus($attribute, self::DELTA);

                $profile = $this->evaluator->evaluate(new TeamSetup($modified, $formation, $mentality), $opponent, self::MATCHES);

                $attributes[$attribute] = [
                    'goals' => round($profile->goalsPer90 - $baseline->goalsPer90, 3),
                    'conceded' => round($profile->goalsConcededPer90 - $baseline->goalsConcededPer90, 3),
                ];
            }

            $rows[] = [
                'slot' => $slot,
                'name' => $assignment->player->name,
                'attributes' => $attributes,
            ];
        }

        return [
            'delta' => self::DELTA,
            'baseline' => [
                'goals' => round($baseline->goalsPer90, 3),
                'conceded' => round($baseline->goalsConcededPer90, 3),
            ],
            'rows' => $rows,
        ];
    }
}
