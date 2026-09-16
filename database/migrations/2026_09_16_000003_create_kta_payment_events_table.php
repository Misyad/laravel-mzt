<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paymenku payment events already received (PRD v3.0 §24).
     *
     * Used for idempotency, audit and debugging. The full raw payload is NOT
     * stored (may contain contact data); only a SHA-256 hash of the raw body is
     * kept so duplicates can be recognised without persisting sensitive fields.
     */
    public function up()
    {
        Schema::create('kta_payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kta_print_request_id')->nullable();
            $table->string('provider', 30)->default('paymenku');
            $table->string('event_type', 60)->nullable();
            $table->string('trx_id')->nullable();
            $table->string('reference_id', 60)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->dateTime('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('kta_print_request_id');
            $table->index('trx_id');
            $table->index('payload_hash');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kta_payment_events');
    }
};
