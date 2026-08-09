<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2C — check-in columns for `prisensi_kehadiran` (PRD §16.10).
     *
     * Additive + idempotent, following the M1/M3 patterns:
     *  - columns guarded by Schema::hasColumn,
     *  - indexes guarded via information_schema.statistics (Laravel 9 has no
     *    Schema::hasIndex).
     *
     * All columns are nullable so the legacy POST /attendance path keeps
     * working untouched. Indexes only — no strict FK (Audit-Gap §4 / Phase 2B).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('prisensi_kehadiran', function (Blueprint $table) {
            if (!Schema::hasColumn('prisensi_kehadiran', 'id_ticket')) {
                $table->unsignedBigInteger('id_ticket')->nullable();
            }
            if (!Schema::hasColumn('prisensi_kehadiran', 'gate')) {
                $table->string('gate', 100)->nullable();
            }
            if (!Schema::hasColumn('prisensi_kehadiran', 'scanned_at')) {
                $table->dateTime('scanned_at')->nullable();
            }
            if (!Schema::hasColumn('prisensi_kehadiran', 'scanned_by')) {
                $table->unsignedBigInteger('scanned_by')->nullable();
            }
        });

        $db = DB::connection()->getDatabaseName();

        $hasIndex = function (string $indexName) use ($db) {
            return DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = 'prisensi_kehadiran'
                AND index_name = ?", [$db, $indexName])->c > 0;
        };

        if (!$hasIndex('prisensi_kehadiran_id_ticket_index')) {
            DB::statement('ALTER TABLE prisensi_kehadiran
                ADD INDEX prisensi_kehadiran_id_ticket_index (id_ticket)');
        }

        if (!$hasIndex('prisensi_kehadiran_id_event_id_tanggal_index')) {
            DB::statement('ALTER TABLE prisensi_kehadiran
                ADD INDEX prisensi_kehadiran_id_event_id_tanggal_index (id_event, id_tanggal)');
        }
    }

    /**
     * Reverse the migrations: drop indexes then guarded columns.
     *
     * @return void
     */
    public function down()
    {
        $db = DB::connection()->getDatabaseName();

        $hasIndex = function (string $indexName) use ($db) {
            return DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = 'prisensi_kehadiran'
                AND index_name = ?", [$db, $indexName])->c > 0;
        };

        if ($hasIndex('prisensi_kehadiran_id_event_id_tanggal_index')) {
            DB::statement('ALTER TABLE prisensi_kehadiran DROP INDEX prisensi_kehadiran_id_event_id_tanggal_index');
        }
        if ($hasIndex('prisensi_kehadiran_id_ticket_index')) {
            DB::statement('ALTER TABLE prisensi_kehadiran DROP INDEX prisensi_kehadiran_id_ticket_index');
        }

        Schema::table('prisensi_kehadiran', function (Blueprint $table) {
            foreach (['scanned_by', 'scanned_at', 'gate', 'id_ticket'] as $column) {
                if (Schema::hasColumn('prisensi_kehadiran', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
