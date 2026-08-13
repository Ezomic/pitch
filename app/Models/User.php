<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $current_career_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $login_code_hash
 * @property Carbon|null $login_code_expires_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'current_career_id'])]
#[Hidden(['login_code_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The save being played. A manager can hold several independent careers, so
     * everything below is scoped to this one rather than to the user: two saves
     * must never see each other's squad, season or players.
     *
     * Resolved lazily and remembered, so a manager who has never started one is
     * not a special case anywhere else.
     */
    public function currentCareer(): Career
    {
        $career = $this->current_career_id !== null
            ? Career::query()->whereKey($this->current_career_id)->where('user_id', $this->id)->first()
            : null;

        $career ??= $this->careers()->orderBy('id')->first();

        if ($career === null) {
            $career = $this->careers()->create([
                'name' => 'My career',
                'type' => Career::SOLO,
                'status' => 'active',
                'last_played_at' => now(),
            ]);
        }

        if ($this->current_career_id !== $career->id) {
            $this->forceFill(['current_career_id' => $career->id])->save();
        }

        return $career;
    }

    public function currentCareerId(): int
    {
        return $this->currentCareer()->id;
    }

    /**
     * @return HasOne<Squad, $this>
     */
    public function squad(): HasOne
    {
        return $this->hasOne(Squad::class)->where('career_id', $this->currentCareerId());
    }

    /**
     * The one active campaign. Completed seasons are kept for history but only a
     * single season is ever in progress at a time.
     *
     * @return HasOne<Season, $this>
     */
    public function season(): HasOne
    {
        return $this->hasOne(Season::class)
            ->where('career_id', $this->currentCareerId())
            ->whereNull('completed_at');
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->where('career_id', $this->currentCareerId());
    }

    /**
     * The manager's saves; each one is an independent game world.
     *
     * @return HasMany<Career, $this>
     */
    public function careers(): HasMany
    {
        return $this->hasMany(Career::class);
    }

    /**
     * @return HasMany<Scout, $this>
     */
    public function scouts(): HasMany
    {
        return $this->hasMany(Scout::class)->where('career_id', $this->currentCareerId());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_code_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
