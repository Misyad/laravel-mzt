<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tickets (PRD §16.8).
     *
     * Public identity = UUID (QR payload = ticket UUID, PRD §10.6/§23.9).
     * nomor_ticket = TKT-YYYY-NNNNNN (PRD §10.5). Status follows TicketStatus
     * enum (draft/issued/checked_in/finished/cancelled/revoked — PRD §16.8/§10.2).
     *
     * Additive + idempotent (Phase 2B). Indexes only, no strict FK (Audit-Gap §4).
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_ticket', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('qr_payload');
            $table->string('status', 30)->default('issued');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_order');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tickets');
    }
};