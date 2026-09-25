<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('event_payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('provider', 30)->default('paymenku');
            $table->string('event_type', 60)->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('status', 30)->nullable();
            $table->decimal('gateway_total', 12, 2)->nullable();
            $table->string('payload_hash', 64)->nullable()->unique();
            $table->boolean('signature_valid')->default(false);
            $table->string('outcome', 40)->nullable();
            $table->string('note')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
            $table->index('transaction_id');
            $table->index('reference');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_payment_events');
    }
};
