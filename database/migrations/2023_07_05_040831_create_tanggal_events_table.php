<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tanggal_events', function (Blueprint $table) {
            $table->id();
            $table->integer('id_event');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->string('jam_selesai')->nullable();
            $table->enum('set_jam', ['seharian', 'dijam'])->default('seharian');
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
        Schema::dropIfExists('tanggal_events');
    }
};
