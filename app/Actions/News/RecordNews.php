<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;

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
            'season_id' => $seasonId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
        ]);
    }
}
