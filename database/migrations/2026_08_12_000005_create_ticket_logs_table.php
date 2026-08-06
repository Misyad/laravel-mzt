<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ticket audit trail (PRD §16.9).
     *
     * Immutable log of the ticket lifecycle (issued/reissued/revoked/used).
     * Index (id_ticket, created_at) supports timeline queries (PRD §16.14).
     *
     * Additive + idempotent (Phase 2B). Indexes only, no strict FK.
     */
    public function up()
    {
        Schema::create('ticket_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_ticket');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_ticket', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_logs');
    }
};