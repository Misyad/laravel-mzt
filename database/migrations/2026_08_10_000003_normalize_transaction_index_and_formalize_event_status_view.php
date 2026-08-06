<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * M3 — Phase 2A:
     *  1. Retire old UNIQUE on `m_transaksi_events.id_anggota` (blocks
     *     multi-event registrations) and replace with UNIQUE
     *     `(id_event, id_anggota)`. Verified: 0 duplicate pairs in prod.
     *  2. Formalize the legacy `event_status` VIEW as an idempotent migration so
     *     a fresh boot also gets it. Name/columns/values stay identical to keep
     *     the legacy admin dashboard working (typo `Complate`/`Upcomming`
     *     preserved for compatibility).
     *
     * Uses raw SQL + information_schema checks so it is safe to re-run.
     *
     * @return void
     */
    public function up()
    {
        $db = DB::connection()->getDatabaseName();

        // --- 1. m_transaksi_events uniqueness ---------------------------------
        $oldUnique = 'm_transaksi_events_id_anggota_unique';
        $newUnique = 'm_transaksi_events_id_event_id_anggota_unique';

        $hasIndex = function (string $indexName) use ($db) {
            return DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = 'm_transaksi_events'
                AND index_name = ?", [$db, $indexName])->c > 0;
        };

        // Drop the legacy singleton-unique index if present.
        if ($hasIndex($oldUnique)) {
            DB::statement("ALTER TABLE m_transaksi_events DROP INDEX $oldUnique");
        }

        // Add composite unique on (id_event, id_anggota) if not already present.
        if (!$hasIndex($newUnique)) {
            DB::statement("ALTER TABLE m_transaksi_events
                ADD UNIQUE INDEX $newUnique (id_event, id_anggota)");
        }

        // --- 2. event_status VIEW ---------------------------------------------
        $view = DB::select("SELECT COUNT(*) AS c FROM information_schema.views
            WHERE table_schema = ? AND table_name = 'event_status'", [$db]);
        if ($view[0]->c === 0) {
            DB::statement(
                "CREATE VIEW event_status AS
                SELECT
                    events.lokasi AS lokasi,
                    events.slug AS slug,
                    events.banner AS banner,
                    events.id AS id,
                    events.tanggal AS tanggal,
                    events.judul_event AS judul_event,
                    events.deskripsi AS deskripsi,
                    events.tanggal_mulai AS tanggal_mulai,
                    events.tanggal_selesai AS tanggal_selesai,
                    events.harga AS harga,
                    events.is_active AS is_active,
                    CASE
                        WHEN events.tanggal_mulai <= CURDATE()
                             AND events.tanggal_selesai >= CURDATE()
                             THEN _utf8mb4 'Ongoing'
                        WHEN events.tanggal_selesai < CURDATE()
                             THEN _utf8mb4 'Complate'
                        WHEN events.tanggal_mulai > CURDATE()
                             THEN _utf8mb4 'Upcomming'
                    END COLLATE utf8mb4_unicode_ci AS status
                FROM events"
            );
        }
    }

    /**
     * Reverse the migrations: restore the single update unique index and
     * drop the formalized view (only if it matches our formalized definition).
     *
     * @return void
     */
    public function down()
    {
        $db = DB::connection()->getDatabaseName();
        $oldUnique = 'm_transaksi_events_id_anggota_unique';
        $newUnique = 'm_transaksi_events_id_event_id_anggota_unique';

        $found = DB::select("SELECT COUNT(*) AS c FROM information_schema.statistics
            WHERE table_schema = ? AND index_name = ?", [$db, $newUnique]);
        if ($found[0]->c > 0) {
            DB::statement("ALTER TABLE m_transaksi_events DROP INDEX $newUnique");
        }

        $old = DB::select("SELECT COUNT(*) AS c FROM information_schema.statistics
            WHERE table_schema = ? AND index_name = ?", [$db, $oldUnique]);
        if ($old[0]->c === 0) {
            DB::statement("ALTER TABLE m_transaksi_events
                ADD UNIQUE INDEX $oldUnique (id_anggota)");
        }

        $view = DB::select("SELECT COUNT(*) AS c FROM information_schema.views
            WHERE table_schema = ? AND table_name = 'event_status'", [$db]);
        if ($view[0]->c > 0) {
            DB::statement("DROP VIEW event_status");
        }
    }
};