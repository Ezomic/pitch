<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Season\EnsureSeason;
use App\Models\CupTie;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CupController extends Controller
{
    public function show(Request $request, EnsureSeason $ensureSeason): Response
    {
        $season = $ensureSeason->handle($this->user($request));
        /** @var Collection<int, Team> $teams */
        $teams = Team::query()->where('is_youth', false)->get()->keyBy('id');

        $ties = $season->cupTies()->get();
        $roundCount = (int) ($ties->max('round') ?? 0);

        return Inertia::render('Cup', [
            'seasonNumber' => $season->number,
            'rounds' => $this->rounds($ties, $teams),
            'champion' => $this->champion($ties, $teams),
            'userOut' => $this->userOut($season, $ties, $roundCount),
        ]);
    }

    /**
     * @param  Collection<int, CupTie>  $ties
     * @param  Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    private function rounds(Collection $ties, Collection $teams): array
    {
        $grouped = $ties->groupBy('round');
        $rounds = [];

        foreach ($grouped as $round => $roundTies) {
            $rounds[] = [
                'round' => (int) $round,
                'name' => $this->roundName($roundTies->count()),
                'ties' => $roundTies->sortBy('slot')->values()->map(fn (CupTie $tie) => [
                    'homeName' => $this->entrantName($tie->home, $teams),
                    'awayName' => $tie->isBye() ? 'Bye' : $this->entrantName($tie->away, $teams),
                    'homeGoals' => $tie->home_goals,
                    'awayGoals' => $tie->away_goals,
                    'played' => $tie->played,
                    'bye' => $tie->isBye(),
                    'involvesUser' => $tie->involvesUser(),
                    'winnerName' => $tie->winner === null ? null : $this->entrantName($tie->winner, $teams),
                    'userWon' => $tie->winner === CupTie::USER,
                ])->all(),
            ];
        }

        return $rounds;
    }

    /**
     * @param  Collection<int, CupTie>  $ties
     * @param  Collection<int, Team>  $teams
     */
    private function champion(Collection $ties, Collection $teams): ?string
    {
        $final = $ties->where('round', $ties->max('round'))->first();

        if (! $final instanceof CupTie || ! $final->played || $ties->where('round', $final->round)->count() !== 1) {
            return null;
        }

        return $final->winner === null ? null : $this->entrantName($final->winner, $teams);
    }

    /**
     * The round the user was knocked out in, or null if still in or already champion.
     *
     * @param  Collection<int, CupTie>  $ties
     */
    private function userOut(Season $season, Collection $ties, int $roundCount): ?int
    {
        for ($round = 1; $round <= $roundCount; $round++) {
            $tie = $ties->first(fn (CupTie $t) => $t->round === $round && $t->involvesUser());

            if ($tie instanceof CupTie && $tie->played && $tie->winner !== CupTie::USER) {
                return $round;
            }
        }

        return null;
    }

    private function roundName(int $tieCount): string
    {
        return match ($tieCount) {
            1 => 'Final',
            2 => 'Semi-finals',
            4 => 'Quarter-finals',
            8 => 'Round of 16',
            default => 'Round',
        };
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function entrantName(?string $entrant, Collection $teams): string
    {
        if ($entrant === CupTie::USER || $entrant === null) {
            return 'Your squad';
        }

        $team = $teams->get((int) $entrant);

        return $team instanceof Team ? $team->name : 'Unknown';
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
