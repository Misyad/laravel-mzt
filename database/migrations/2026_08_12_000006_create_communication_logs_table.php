<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound communication log (ADR-016, PRD §20.13).
     *
     * Auditable record of every communication delivered via the Communication
     * Engine. Never deleted (PRD §20.13). Driven by domain events + queue.
     *
     * Additive + idempotent (Phase 2B). Indexes only, no strict FK.
     */
    public function up()
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event', 100)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 50);
            $table->string('provider', 100)->nullable();
            $table->string('template', 191)->nullable();
            $table->string('status', 30)->default('queued');
            $table->text('response')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('delivered_at')->nullable();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('communication_logs');
    }
};