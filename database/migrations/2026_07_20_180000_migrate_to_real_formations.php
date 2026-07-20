<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $map = [
        'balanced' => '433',
        'defensive' => '532',
        'attacking' => '343',
    ];

    public function up(): void
    {
        foreach (['squads', 'teams'] as $table) {
            foreach ($this->map as $old => $new) {
                DB::table($table)->where('formation', $old)->update(['formation' => $new]);
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('formation')->default('433')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['squads', 'teams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('formation')->default('balanced')->change();
            });

            foreach ($this->map as $old => $new) {
                DB::table($table)->where('formation', $new)->update(['formation' => $old]);
            }
        }
    }
};
