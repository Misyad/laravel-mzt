<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KTA print request audit trail (PRD v3.0 §25).
     *
     * Immutable log of every status transition. Automatic payment transitions
     * are recorded with actor = NULL and source = 'paymenku_webhook'.
     *
     * Additive + idempotent. Index (kta_print_request_id, created_at) supports
     * timeline queries.
     */
    public function up()
    {
        Schema::create('kta_print_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kta_print_request_id');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            $table->string('source', 40)->default('admin');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kta_print_request_id', 'created_at'], 'kta_print_logs_request_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kta_print_request_logs');
    }
};
