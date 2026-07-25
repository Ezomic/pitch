<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $season_id
 * @property string $category
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $read_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'season_id', 'category', 'title', 'body', 'payload', 'read_at', 'resolved_at'])]
class News extends Model
{
    protected $table = 'news';

    public const string RESULT = 'result';

    public const string BOARD = 'board';

    public const string OFFER = 'offer';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A transfer offer the user has not yet accepted or declined.
     *
     * @param  Builder<News>  $query
     */
    public function scopeOpenOffers(Builder $query): void
    {
        $query->where('category', self::OFFER)->whereNull('resolved_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
