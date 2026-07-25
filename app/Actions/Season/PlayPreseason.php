<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\News\RecordNews;
use App\Actions\Squad\EnsureSquad;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadPlayer;
use App\Models\Team;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;
use Illuminate\Support\Facades\DB;

/**
 * Play out the preseason friendlies. Each one is resolved for the record and,
 * because these are conditioning runs rather than results that matter, sharpens
 * the first team: the fielded players finish the preseason fully fit and a touch
 * more in form for the opening day.
 */
class PlayPreseason
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
        private readonly EnsureSquad $ensureSquad = new EnsureSquad,
        private readonly RecordNews $recordNews = new RecordNews,
    ) {}

    public function handle(Season $season): void
    {
        $pending = $season->friendlies()->where('played', false)->get();

        if ($pending->isEmpty()) {
            return;
        }

        $squad = $this->ensureSquad->handle($season->user);
        $userSetup = $squad->setup();

        DB::transaction(function () use ($season, $pending, $squad, $userSetup): void {
            foreach ($pending as $friendly) {
                $opponent = Team::find($friendly->opponent_team_id);
                $opponentSetup = $opponent instanceof Team ? $opponent->setup() : TeamSetup::baseline();
                $opponentName = $opponent instanceof Team ? $opponent->name : 'a rival';

                $result = $friendly->home
                    ? $this->resolver->resolve($userSetup, $opponentSetup, $friendly->seed)
                    : $this->resolver->resolve($opponentSetup, $userSetup, $friendly->seed);

                [$userGoals, $oppGoals] = $friendly->home
                    ? [$result['home'], $result['away']]
                    : [$result['away'], $result['home']];

                $friendly->forceFill([
                    'user_goals' => $userGoals,
                    'opponent_goals' => $oppGoals,
                    'played' => true,
                ])->save();

                $this->recordNews->handle(
                    userId: $season->user_id,
                    category: News::RESULT,
                    title: 'Friendly '.$userGoals.'-'.$oppGoals.' '.($friendly->home ? 'vs' : 'at').' '.$opponentName,
                    body: 'A preseason friendly, building fitness before the league starts.',
                    seasonId: $season->id,
                );
            }

            $this->sharpen($squad->id);
        });
    }

    /** The first team comes out of preseason fully fit and slightly sharper. */
    private function sharpen(int $squadId): void
    {
        $playerIds = SquadPlayer::query()->where('squad_id', $squadId)->pluck('player_id');

        foreach (Player::query()->whereIn('id', $playerIds)->get() as $player) {
            $player->forceFill([
                'fitness' => Player::FITNESS_MAX,
                'form' => min(Player::FORM_MAX, $player->form + 1),
            ])->save();
        }
    }
}
