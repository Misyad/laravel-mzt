<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive audit columns for Phase 1 (Digital Identity Foundation).
     * Idempotent: each column is only created when absent, so it is safe to
     * run repeatedly without renames or data loss.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'login_count')) {
                $table->unsignedInteger('login_count')->default(0);
            }
        });

        // Keep every pre-existing account working as-is (do not force them to
        // change their password). Only accounts that still have
        // password_changed_at = NULL (created / reset after this migration)
        // will trigger the first-login forced password change.
        DB::table('users')->whereNull('password_changed_at')->update([
            'password_changed_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_login', 'password_changed_at', 'login_count'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};