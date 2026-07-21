<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\TeamSetup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $budget
 * @property int $bank
 * @property string $formation
 * @property string $mentality
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'budget', 'bank', 'formation', 'mentality'])]
class Squad extends Model
{
    public const int DEFAULT_BUDGET = 220;

    public const int DEFAULT_BANK = 300;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'formation' => '433',
        'mentality' => 'balanced',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The player-to-slot assignments that make up this squad.
     *
     * @return HasMany<SquadPlayer, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SquadPlayer::class)->orderBy('slot');
    }

    /** A player fielded out of their natural position plays at this fraction. */
    private const float OFF_POSITION_FACTOR = 0.9;

    /**
     * The squad's attributes keyed by formation slot, with empty slots filled by
     * an average placeholder so the engine always has a full eleven. A player
     * fielded away from their natural position takes an off-position penalty.
     *
     * @return array<int, Attributes>
     */
    public function attributesBySlot(): array
    {
        $formation = Formation::fromId($this->formation);
        $bySlot = [];

        foreach ($this->assignments()->with('player')->get() as $assignment) {
            $bySlot[$assignment->slot] = $this->slotAttributes($assignment->player, $assignment->slot, $formation, $assignment->role);
        }

        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] ??= new Attributes(50, 50, 50, 50, 50, 50);
        }

        return $bySlot;
    }

    private function slotAttributes(Player $player, int $slot, Formation $formation, ?string $role = null): Attributes
    {
        $attributes = $player->matchAttributes();

        foreach (SquadPlayer::ROLES[$role] ?? [] as $attribute => $delta) {
            $attributes = $attributes->plus($attribute, $delta);
        }

        $slotPosition = $formation->layout[$slot][1] ?? null;

        if ($slotPosition !== null && $slotPosition !== $player->position) {
            $attributes = $attributes->scaled(self::OFF_POSITION_FACTOR);
        }

        return $attributes;
    }

    public function setup(): TeamSetup
    {
        return new TeamSetup(
            $this->attributesBySlot(),
            Formation::fromId($this->formation),
            Mentality::fromId($this->mentality),
        );
    }

    /**
     * Build a setup from an explicit slot => playerId lineup (used for live-match
     * substitutions), keeping the squad's own formation and mentality.
     *
     * @param  array<int, int>  $lineup  slot id => player id
     */
    public function setupFrom(array $lineup): TeamSetup
    {
        $players = Player::query()->whereIn('id', array_values($lineup))->get()->keyBy('id');
        $formation = Formation::fromId($this->formation);
        $roles = $this->assignments()->pluck('role', 'slot');

        $bySlot = [];
        foreach ($lineup as $slot => $playerId) {
            $player = $players->get($playerId);
            $bySlot[(int) $slot] = $player instanceof Player
                ? $this->slotAttributes($player, (int) $slot, $formation, $roles->get((int) $slot))
                : new Attributes(50, 50, 50, 50, 50, 50);
        }

        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] ??= new Attributes(50, 50, 50, 50, 50, 50);
        }

        return new TeamSetup(
            $bySlot,
            $formation,
            Mentality::fromId($this->mentality),
        );
    }
}
