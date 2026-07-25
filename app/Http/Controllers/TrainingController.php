<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $seniors = Player::query()
            ->where('user_id', $user->id)
            ->where('is_youth', false)
            ->orderByDesc('potential')
            ->orderBy('name')
            ->get();

        return Inertia::render('Training', [
            'players' => $seniors->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position->value,
                'age' => $player->age,
                'overall' => $player->overall(),
                'potential' => $player->potential,
                'fitness' => $player->fitness,
                'trainingFocus' => $player->training_focus,
                'atCeiling' => $player->overall() >= min(99, $player->potential),
                'attributes' => [
                    'vision' => $player->vision,
                    'passing' => $player->passing,
                    'dribbling' => $player->dribbling,
                    'finishing' => $player->finishing,
                    'tackling' => $player->tackling,
                    'pace' => $player->pace,
                ],
            ])->all(),
        ]);
    }

    public function focus(Request $request, Player $player): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id && ! $player->is_youth, 404);

        $data = $request->validate([
            'focus' => ['nullable', Rule::in(Player::ATTRIBUTES)],
        ]);

        $player->update(['training_focus' => $data['focus'] ?? null]);

        return to_route('training.index');
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
