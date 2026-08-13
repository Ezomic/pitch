<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;
use App\Models\Season;
use App\Models\User;

/**
 * Drop a single item into the user's news feed. The one place items are created,
 * so results, board messages and transfer offers all read the same way.
 */
class RecordNews
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(
        int $userId,
        string $category,
        string $title,
        string $body,
        ?int $seasonId = null,
        ?array $payload = null,
    ): News {
        return News::create([
            'user_id' => $userId,
            'career_id' => $this->careerFor($userId, $seasonId),
            'season_id' => $seasonId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
        ]);
    }

    /**
     * Which save this belongs to. The season it was filed against knows, and
     * when there is none the manager's active career does. Without this an item
     * would show up in every save the manager holds.
     */
    private function careerFor(int $userId, ?int $seasonId): ?int
    {
        if ($seasonId !== null) {
            $career = Season::query()->whereKey($seasonId)->value('career_id');

            if ($career !== null) {
                return (int) $career;
            }
        }

        return User::query()->find($userId)?->currentCareerId();
    }
}
