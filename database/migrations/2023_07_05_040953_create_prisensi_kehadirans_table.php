<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->integer('id_tanggal');
            $table->integer('id_anggota');
            $table->timestamp('id_user')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $table->timestamp('tanggal_kehadiran')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $table->time('jam_kehadiran');
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
