<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
            $table->string('login_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('login_code_expires_at')->nullable()->after('login_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->after('email_verified_at');
            $table->dropColumn(['login_code_hash', 'login_code_expires_at']);
        });
    }
};
