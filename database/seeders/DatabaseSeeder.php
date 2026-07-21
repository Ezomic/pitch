<?php

namespace Database\Seeders;

use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PlayerSeeder::class, TeamSeeder::class]);

        if (! app()->isProduction()) {
            $this->seedDevUser();
        }
    }

    /**
     * A permanent local account with a ready squad and season, so a full
     * `migrate:fresh --seed` always leaves an account to sign straight into with
     * the dev login code. Idempotent, and never seeded in production.
     */
    private function seedDevUser(): void
    {
        $user = User::firstOrCreate(
            ['email' => config('auth.dev_login_email')],
            ['name' => 'Dev Manager'],
        );

        app(EnsureSquad::class)->handle($user);
        app(EnsureSeason::class)->handle($user);
    }
}
