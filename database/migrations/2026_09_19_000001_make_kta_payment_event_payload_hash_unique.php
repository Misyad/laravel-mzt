<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $duplicates = DB::table('kta_payment_events')
            ->whereNotNull('payload_hash')
            ->groupBy('payload_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('payload_hash');

        foreach ($duplicates as $payloadHash) {
            $keepId = DB::table('kta_payment_events')
                ->where('payload_hash', $payloadHash)
                ->orderByRaw('processed_at IS NULL')
                ->orderBy('id')
                ->value('id');

            DB::table('kta_payment_events')
                ->where('payload_hash', $payloadHash)
                ->where('id', '<>', $keepId)
                ->update(['payload_hash' => null]);
        }

        Schema::table('kta_payment_events', function (Blueprint $table) {
            $table->dropIndex(['payload_hash']);
            $table->unique('payload_hash');
        });
    }

    public function down()
    {
        Schema::table('kta_payment_events', function (Blueprint $table) {
            $table->dropUnique(['payload_hash']);
            $table->index('payload_hash');
        });
    }
};
