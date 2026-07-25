<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Actions\Squad\EnsureSquad;
use App\Models\CupTie;
use App\Models\Season;
use App\Models\Team;
use App\Sim\Engine\Rng;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Play the cup's next outstanding round: resolve each tie, carry the winners into
 * a freshly drawn following round, and stop once one club is left holding the cup.
 * A level tie is settled by a deterministic draw from its own seed, so knockouts
 * always produce a winner and the same season always plays out identically.
 */
class PlayCupRound
{
    public function __construct(
        private readonly FixtureResolver $resolver = new FixtureResolver,
        private readonly EnsureSquad $ensureSquad = new EnsureSquad,
    ) {}

    public function handle(Season $season): void
    {
        $round = $season->cupTies()->where('played', false)->min('round');

        if ($round === null) {
            return;
        }

        DB::transaction(function () use ($season, $round): void {
            $userSetup = $this->ensureSquad->handle($season->user)->setup();
            /** @var Collection<int, Team> $teams */
            $teams = Team::query()->where('is_youth', false)->get()->keyBy('id');

            $ties = $season->cupTies()->where('round', $round)->get();

            foreach ($ties->where('played', false) as $tie) {
                $this->resolve($tie, $userSetup, $teams);
            }

            $this->drawNextRound($season, $round);
        });
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function resolve(CupTie $tie, TeamSetup $userSetup, Collection $teams): void
    {
        $result = $this->resolver->resolve(
            $this->setupFor($tie->home, $userSetup, $teams),
            $this->setupFor($tie->away, $userSetup, $teams),
            $tie->seed,
        );

        $winner = match (true) {
            $result['home'] > $result['away'] => $tie->home,
            $result['away'] > $result['home'] => $tie->away,
            default => (new Rng($tie->seed))->next() < 0.5 ? $tie->home : $tie->away,
        };

        $tie->forceFill([
            'home_goals' => $result['home'],
            'away_goals' => $result['away'],
            'winner' => $winner,
            'played' => true,
        ])->save();
    }

    private function drawNextRound(Season $season, int $round): void
    {
        $winners = $season->cupTies()->where('round', $round)->orderBy('slot')
            ->pluck('winner')->filter()->values();

        if ($winners->count() < 2) {
            return;
        }

        $slot = 0;
        for ($i = 0; $i < $winners->count(); $i += 2) {
            $home = $winners[$i];
            $away = $winners[$i + 1] ?? null;

            $season->cupTies()->create([
                'round' => $round + 1,
                'slot' => $slot,
                'home' => $home,
                'away' => $away,
                'played' => $away === null,
                'winner' => $away === null ? $home : null,
                'seed' => $season->id * 1000 + 700 + ($round + 1) * 20 + $slot,
            ]);

            $slot++;
        }
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function setupFor(?string $entrant, TeamSetup $userSetup, Collection $teams): TeamSetup
    {
        if ($entrant === CupTie::USER || $entrant === null) {
            return $userSetup;
        }

        $team = $teams->get((int) $entrant);

        return $team instanceof Team ? $team->setup() : TeamSetup::baseline();
    }
}
