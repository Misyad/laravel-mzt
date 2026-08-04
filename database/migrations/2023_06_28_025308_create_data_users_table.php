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
        Schema::create('data_users', function (Blueprint $table) {
            $table->id();
            $table->integer('id_users');
            $table->string('no_hp')->nullable();
            $table->text('barcode')->nullable();
            $table->text('alamat');
            $table->string('pekerjaan');
            $table->string('niqobah');
            $table->date('tanggal_lahir');
            $table->date('tahun_masuk');
            $table->date('tahun_keluar');
            $table->text('foto');
            $table->enum('is_active', ['1', '0'])->default('1');
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
        Schema::dropIfExists('data_users');
    }
};
