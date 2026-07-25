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
 * @property int $weekly_income
 * @property int $division
 * @property int|null $goalkeeper_id
 * @property string $formation
 * @property string $mentality
 * @property array<int, array{int, int}>|null $custom_formation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'budget', 'bank', 'weekly_income', 'division', 'goalkeeper_id', 'formation', 'mentality', 'custom_formation'])]
class Squad extends Model
{
    /** The keeper rating used when no goalkeeper is assigned: a stand-in reserve. */
    public const int DEFAULT_KEEPING = 45;

    public const int DEFAULT_BUDGET = 220;

    public const int DEFAULT_BANK = 300;

    /** Cash the club takes in each week, drawn against the wage bill. */
    public const int DEFAULT_WEEKLY_INCOME = 20;

    /** The toughest tier a club can reach; a promotion from here is not possible. */
    public const int TOP_DIVISION = 1;

    /** The lowest tier, where clubs start and where relegation bottoms out. */
    public const int BOTTOM_DIVISION = 2;

    public const int DEFAULT_DIVISION = self::BOTTOM_DIVISION;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'formation' => '433',
        'mentality' => 'balanced',
        'division' => self::DEFAULT_DIVISION,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_formation' => 'array',
        ];
    }

    /**
     * The shape the squad actually plays: a stored custom layout when one is set,
     * otherwise the chosen preset.
     */
    public function formationObject(): Formation
    {
        if ($this->formation === Formation::CUSTOM_ID && is_array($this->custom_formation)) {
            return Formation::custom($this->custom_formation);
        }

        return Formation::fromId($this->formation);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function goalkeeper(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'goalkeeper_id');
    }

    /**
     * The keeper rating the engine defends with: the assigned goalkeeper's
     * shot-stopping, or a weak reserve level when no keeper is chosen.
     */
    public function goalkeeping(): int
    {
        $keeper = $this->goalkeeper;

        return $keeper instanceof Player ? $keeper->keeperRating() : self::DEFAULT_KEEPING;
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

    /**
     * The senior players the club pays: everyone it owns bar academy prospects.
     *
     * @return HasMany<Player, $this>
     */
    public function seniors(): HasMany
    {
        return $this->hasMany(Player::class, 'user_id', 'user_id')->where('is_youth', false);
    }

    /** The combined weekly wages of every senior on the books. */
    public function wageBill(): int
    {
        return (int) $this->seniors()->get()->sum(fn (Player $player) => $player->weeklyWage());
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
        $formation = $this->formationObject();
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
            $this->formationObject(),
            Mentality::fromId($this->mentality),
            $this->goalkeeping(),
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
        $formation = $this->formationObject();
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
            $this->goalkeeping(),
        );
    }
}
