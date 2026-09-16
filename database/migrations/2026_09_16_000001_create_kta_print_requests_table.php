<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Physical KTA print requests (PRD v3.0).
     *
     * One active request per member. Concurrency is enforced by a unique index
     * on `active_key`, which holds the user id while the request is active and
     * is set to NULL once the request becomes terminal (selesai/ditolak/
     * pembayaran_expired). MySQL treats NULLs as distinct in unique indexes, so
     * terminal rows do not block a new request while concurrent inserts for the
     * same member collide deterministically.
     *
     * Payment fields mirror the Paymenku contract:
     *   provider / reference (KTA-{id}) / trx_id / amount / status / paid_at.
     *
     * Additive + idempotent. Indexes only, no strict FK (repo convention).
     */
    public function up()
    {
        Schema::create('kta_print_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_users');
            $table->string('id_anggota_snapshot', 50);

            // Active lifecycle: menunggu_pembayaran → menunggu_cetak →
            // sudah_dicetak → (siap_diambil|dikirim) → selesai.
            // Terminal: selesai, ditolak, pembayaran_expired.
            $table->string('status', 30)->default('menunggu_pembayaran');
            $table->string('delivery_method', 20)->default('pickup');

            // Payment (Paymenku).
            $table->string('payment_provider', 30)->nullable();
            $table->string('payment_reference', 60)->nullable();
            $table->string('payment_trx_id')->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_channel', 30)->nullable();
            $table->text('pay_url')->nullable();

            // Delivery (only meaningful when delivery_method = delivery).
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->text('shipping_address')->nullable();

            // Lifecycle timestamps.
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('printed_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('rejected_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('printed_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();

            // Unique-per-active-member guard (NULL for terminal rows).
            $table->unsignedBigInteger('active_key')->nullable()->unique();

            $table->timestamps();

            $table->index('id_users');
            $table->index('status');
            $table->index('delivery_method');
            $table->index('payment_reference');
            $table->index('payment_trx_id');
            $table->index('payment_status');
            $table->index('submitted_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kta_print_requests');
    }
};
