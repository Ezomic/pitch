<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\News\ResolveOffer;
use App\Actions\Squad\EnsureSquad;
use App\Models\News;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $items = News::query()->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(60)->get();

        $payload = $items->map(fn (News $item) => [
            'id' => $item->id,
            'category' => $item->category,
            'title' => $item->title,
            'body' => $item->body,
            'read' => $item->read_at !== null,
            'date' => $item->created_at?->toDateString(),
            'offer' => $item->category === News::OFFER && $item->resolved_at === null
                ? ['fee' => (int) ($item->payload['fee'] ?? 0)]
                : null,
        ])->all();

        // Mark everything bar still-open offers as read now they have been seen.
        News::query()->where('user_id', $user->id)
            ->whereNull('read_at')
            ->where(fn ($query) => $query->where('category', '!=', News::OFFER)->orWhereNotNull('resolved_at'))
            ->update(['read_at' => now()]);

        return Inertia::render('News', ['items' => $payload]);
    }

    public function accept(Request $request, News $news, EnsureSquad $ensureSquad, ResolveOffer $resolveOffer): RedirectResponse
    {
        $resolveOffer->handle($news, $ensureSquad->handle($this->user($request)), accept: true);

        return to_route('news.index');
    }

    public function decline(Request $request, News $news, EnsureSquad $ensureSquad, ResolveOffer $resolveOffer): RedirectResponse
    {
        $resolveOffer->handle($news, $ensureSquad->handle($this->user($request)), accept: false);

        return to_route('news.index');
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
