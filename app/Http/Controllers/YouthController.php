<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Season\EnsureSeason;
use App\Actions\Season\Standings;
use App\Actions\Youth\LoanOut;
use App\Actions\Youth\PromoteYouth;
use App\Actions\Youth\RecallLoan;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class YouthController extends Controller
{
    public function index(Request $request, EnsureSeason $ensureSeason, Standings $standings): Response
    {
        $user = $this->user($request);
        $season = $ensureSeason->handle($user);

        $prospects = Player::query()
            ->where('user_id', $user->id)
            ->where('is_youth', true)
            ->orderByDesc('potential')
            ->orderByDesc('age')
            ->get();

        $teams = Team::query()->where('is_youth', true)->get()->keyBy('id');

        return Inertia::render('Youth', [
            'prospects' => $prospects->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position->value,
                'age' => $player->age,
                'overall' => $player->overall(),
                'potential' => $player->potential,
                'promotable' => $player->isPromotable() && ! $player->on_loan,
                'onLoan' => $player->on_loan,
                'loanWeeks' => $player->loan_weeks_remaining,
                'trainingFocus' => $player->training_focus,
                'attributes' => [
                    'vision' => $player->vision,
                    'passing' => $player->passing,
                    'dribbling' => $player->dribbling,
                    'finishing' => $player->finishing,
                    'tackling' => $player->tackling,
                    'pace' => $player->pace,
                ],
                'fitness' => $player->fitness,
                'form' => $player->form,
            ])->all(),
            'leagueTable' => $standings->handle($season, youth: true),
            'fixtures' => $season->fixtures()->where('youth', true)->orderBy('matchday')->get()
                ->map(fn (Fixture $fixture) => [
                    'id' => $fixture->id,
                    'opponent' => $teams->get($fixture->away_team_id)->name,
                    'played' => $fixture->played,
                    'goalsFor' => $fixture->home_goals,
                    'goalsAgainst' => $fixture->away_goals,
                ])->all(),
        ]);
    }

    public function promote(Request $request, Player $player, PromoteYouth $promoteYouth): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id && ! $player->on_loan, 404);

        $promoteYouth->handle($player);

        return to_route('youth.index');
    }

    public function loan(Request $request, Player $player, LoanOut $loanOut): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id && $player->is_youth, 404);

        $loanOut->handle($player);

        return to_route('youth.index');
    }

    public function recall(Request $request, Player $player, RecallLoan $recallLoan): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id, 404);

        $recallLoan->handle($player);

        return to_route('youth.index');
    }

    public function focus(Request $request, Player $player): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id && $player->is_youth, 404);

        $data = $request->validate([
            'focus' => ['nullable', Rule::in(Player::ATTRIBUTES)],
        ]);

        $player->update(['training_focus' => $data['focus'] ?? null]);

        return to_route('youth.index');
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
