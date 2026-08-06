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
     * M1 — Phase 2A: additive registration/capacity columns for `events`.
     * Idempotent: each column is only created when absent, so it is safe to run
     * repeatedly (Jenkins runs `migrate --force` on every deploy).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'kuota')) {
                $table->unsignedInteger('kuota')->nullable();
            }
            if (!Schema::hasColumn('events', 'venue')) {
                $table->string('venue')->nullable();
            }
            if (!Schema::hasColumn('events', 'visibility')) {
                $table->string('visibility', 20)->default('public');
            }
            if (!Schema::hasColumn('events', 'registrasi_dibuka')) {
                $table->dateTime('registrasi_dibuka')->nullable();
            }
            if (!Schema::hasColumn('events', 'registrasi_ditutup')) {
                $table->dateTime('registrasi_ditutup')->nullable();
            }
            if (!Schema::hasColumn('events', 'harga_amount')) {
                $table->decimal('harga_amount', 12, 2)->nullable();
            }
        });

        // Backfill `harga_amount` from the legacy `harga` varchar (e.g. "Rp. 100.000").
        // Only fills rows still NULL so it is safe to re-run.
        $rows = DB::table('events')
            ->whereNull('harga_amount')
            ->select(['id', 'harga'])
            ->get();

        foreach ($rows as $row) {
            $amount = $this->parseAmount($row->harga);
            if ($amount !== null) {
                DB::table('events')->where('id', $row->id)->update([
                    'harga_amount' => $amount,
                ]);
            }
        }
    }

    /**
     * Parse a legacy price string like "Rp. 100.000" / "Rp. 1.000" / "159".
     * Indonesian thousands separator (dot) is stripped. Returns null when the
     * value is not a number so invalid data is left for manual resolution.
     *
     * @param  string|null  $raw
     * @return float|null
     */
    protected function parseAmount($raw)
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.]/', '', $raw);
        $clean = str_replace('.', '', $clean);
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }
        return (float) $clean;
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['kuota', 'venue', 'visibility', 'registrasi_dibuka', 'registrasi_ditutup', 'harga_amount'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
