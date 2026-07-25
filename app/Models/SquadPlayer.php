<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $squad_id
 * @property int $player_id
 * @property int $slot
 * @property string|null $role
 */
#[Fillable(['squad_id', 'player_id', 'slot', 'role'])]
class SquadPlayer extends Model
{
    protected $table = 'squad_player';

    /**
     * Tactical roles, each a redistribution of a player's effective attributes:
     * a strength traded for a weakness, so shape becomes a lever beyond formation.
     *
     * @var array<string, array<string, int>>
     */
    public const array ROLES = [
        'ball_playing' => ['passing' => 10, 'vision' => 8, 'tackling' => -10],
        'anchor' => ['tackling' => 12, 'finishing' => -12],
        'creator' => ['vision' => 10, 'passing' => 8, 'pace' => -10],
        'target_man' => ['finishing' => 10, 'pace' => -10],
        'poacher' => ['finishing' => 10, 'pace' => 6, 'passing' => -12],
    ];

    /**
     * @return BelongsTo<Squad, $this>
     */
    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
