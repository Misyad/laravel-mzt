<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns so communication_logs can serve as the Notification Center
 * (Sprint 4, ADR-016). Non-destructive: only nullable columns + an index on the
 * in-app inbox.
 *
 *  - title / message : rendered template text surfaced in the inbox
 *  - payload         : JSON key-value placeholder data used at delivery time
 *  - read_at         : read-state of an in-app notification (owner)
 *
 * This migration does NOT alter the immutable PRD §20.13 business fields
 * (event, user_id, channel, provider, template, status, retry_count,
 * created_at, delivered_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('provider');
            $table->text('message')->nullable()->after('title');
            $table->json('payload')->nullable()->after('message');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->index(['user_id', 'channel'], 'comm_logs_user_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->dropIndex('comm_logs_user_channel_idx');
            $table->dropColumn(['read_at', 'payload', 'message', 'title']);
        });
    }
};