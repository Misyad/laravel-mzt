<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment audit trail (PRD §16.7).
     *
     * Immutable log of every status transition. Never pruned; kept as history
     * (PRD §9.6, §16.7, §16.15). Indexed for timeline queries per PRD §16.14.
     *
     * Additive + idempotent (Phase 2B). Indexes only, no strict FK.
     */
    public function up()
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_payment');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_payment', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_logs');
    }
};