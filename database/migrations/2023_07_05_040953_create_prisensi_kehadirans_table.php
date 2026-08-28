<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * C-03 lineage reconstruction — align the base `prisensi_kehadiran` create
 * with the PRODUCTION runtime contract (source of truth, verified against the
 * production schema dump):
 *
 *   id_anggota   varchar(255) NOT NULL DEFAULT ''   (was: integer)
 *   id_user      bigint      NOT NULL DEFAULT 0     (was: timestamp)
 *   id_tanggal   bigint      NULL                   (was: integer NOT NULL)
 *   jam_kehadiran timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP (was: time)
 *
 * Production has already recorded this migration (batch 2), so editing the
 * file does NOT re-run there and production is untouched. Fresh installs now
 * reproduce the exact runtime contract. Phase 2C additive columns
 * (id_ticket, gate, scanned_at, scanned_by) are layered on by their own
 * migration (2026_08_15_000001) and are unchanged.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prisensi_kehadiran', function (Blueprint $table) {
            $table->id();
            $table->integer('id_event');
            $table->bigInteger('id_tanggal')->nullable();
            $table->string('id_anggota')->default('');
            $table->bigInteger('id_user')->default(0);
            $table->timestamp('tanggal_kehadiran')->nullable()->useCurrent();
            $table->timestamp('jam_kehadiran')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prisensi_kehadiran');
    }
};
