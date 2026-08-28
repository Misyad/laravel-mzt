<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C-03 lineage reconstruction — original production migration
 * `2023_10_17_070405_add_new_colum` (batch 7) was present in the production
 * migration history but missing from the repository, making `events.harga`
 * and `users.jatah_edit` hidden production-only dependencies.
 *
 * Restored under its EXACT original name so that any environment whose
 * `migrations` table already contains it (e.g. current production) skips it,
 * while a fresh install creates both columns.
 *
 * Both adds are guarded with Schema::hasColumn and are safe to re-run
 * (Jenkins runs `migrate --force` on every deploy).
 *
 * @return void
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'harga')) {
                // Production DDL: varchar(255) NOT NULL. A default is added so
                // the ALTER is safe on any non-empty partial state; reads and
                // writes are unaffected (callers always set harga explicitly).
                $table->string('harga')->default('');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'jatah_edit')) {
                // Production DDL: int(11) DEFAULT 0.
                $table->integer('jatah_edit')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'harga')) {
                $table->dropColumn('harga');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'jatah_edit')) {
                $table->dropColumn('jatah_edit');
            }
        });
    }
};
