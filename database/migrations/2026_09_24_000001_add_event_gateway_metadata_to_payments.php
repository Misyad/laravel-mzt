<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('source');
            $table->string('reference', 80)->nullable()->after('provider');
            $table->string('transaction_id')->nullable()->after('reference');
            $table->decimal('base_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('gateway_fee', 12, 2)->nullable()->after('base_amount');
            $table->decimal('gateway_total', 12, 2)->nullable()->after('gateway_fee');
            $table->text('payment_url')->nullable()->after('gateway_total');
            $table->dateTime('expires_at')->nullable()->after('payment_url');

            $table->unique('reference');
            $table->unique(['provider', 'transaction_id']);
            $table->unique(['id_order', 'provider']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropUnique(['provider', 'transaction_id']);
            $table->dropUnique(['id_order', 'provider']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn([
                'provider',
                'reference',
                'transaction_id',
                'base_amount',
                'gateway_fee',
                'gateway_total',
                'payment_url',
                'expires_at',
            ]);
        });
    }
};
