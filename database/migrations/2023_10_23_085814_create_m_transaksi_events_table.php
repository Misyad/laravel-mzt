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
        Schema::create('m_transaksi_events', function (Blueprint $table) {
            $table->id();
            $table->string('id_anggota')->unique();
            $table->string('order_id')->nullable();
            $table->string('gross_amount')->nullable();
            $table->string('payment_code')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('status_code')->nullable();
            $table->string('status_message')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('transaction_status')->nullable();
            $table->date('transaction_time')->nullable();
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
        Schema::dropIfExists('m_transaksi_events');
    }
};
