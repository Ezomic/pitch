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
 */
#[Fillable(['squad_id', 'player_id', 'slot'])]
class SquadPlayer extends Model
{
    protected $table = 'squad_player';

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
