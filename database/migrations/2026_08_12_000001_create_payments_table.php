<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment table (PRD §16.5).
     *
     * Child entity of Order (Aggregate Root, ADR-001). Follows S1 (uuid public),
     * S2 (status VARCHAR + enum, not DB ENUM), S4 (created_by/updated_by audit).
     * Uses plain indexes instead of strict FKs (Audit-Gap §4 anomaly #4) to keep
     * the legacy admin writer from breaking.
     *
     * Additive + idempotent (Phase 2B, PRD §16.15).
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_payment', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('method', 30);
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('id_order');
            $table->index('status');
            $table->index('method');
            $table->index('paid_at');
            $table->index('verified_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};