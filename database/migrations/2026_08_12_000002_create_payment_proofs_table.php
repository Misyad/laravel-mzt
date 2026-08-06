<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment proofs uploads (PRD §16.6).
     *
     * 1:N per payment so re-upload history is preserved (reject -> upload again,
     * PRD §9.6 / §15 Failure Flow). Files live in storage/app/payments (PRD
     * §23.14); only metadata is stored here. uploaded_by = actor who uploaded.
     *
     * Additive + idempotent (Phase 2B). Index only, no strict FK.
     */
    public function up()
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('id_payment');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index('id_payment');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_proofs');
    }
};