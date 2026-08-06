<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * M2 — Phase 2A: `orders`, the root aggregate of EMS.
     * Architecture standards applied:
     *  - S1: `uuid` as the public identity (QR, links, external API).
     *  - S2: status stored as VARCHAR(30) + PHP enum constants, NOT DB ENUM.
     *  - S3: immutable snapshot of the event (name, price, start date).
     *  - S4: `created_by` / `updated_by` audit columns.
     *  - `nomor_order` = MZT-YYYY-NNNNNN, globally unique (admin number).
     *  - UNIQUE (id_event, id_anggota) prevents duplicate registration.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_order', 20)->unique();
            $table->unsignedBigInteger('id_event')->index();
            $table->string('id_anggota')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Immutable snapshot taken at registration time (S3).
            $table->string('event_name');
            $table->decimal('event_price', 12, 2)->default(0);
            $table->date('event_start_at')->nullable();

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status_registrasi', 30)->default('draft');
            $table->string('payment_status', 30)->default('pending');

            $table->timestamps();

            $table->unique(['id_event', 'id_anggota']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
