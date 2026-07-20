<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Youth\PromoteYouth;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class YouthController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $prospects = Player::query()
            ->where('user_id', $user->id)
            ->where('is_youth', true)
            ->orderByDesc('potential')
            ->orderByDesc('age')
            ->get();

        return Inertia::render('Youth', [
            'prospects' => $prospects->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position->value,
                'age' => $player->age,
                'overall' => $player->overall(),
                'potential' => $player->potential,
                'promotable' => $player->isPromotable(),
            ])->all(),
        ]);
    }

    public function promote(Request $request, Player $player, PromoteYouth $promoteYouth): RedirectResponse
    {
        abort_unless($player->user_id === $this->user($request)->id, 404);

        $promoteYouth->handle($player);

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
