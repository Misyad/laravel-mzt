<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kta_price_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kta_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_amount');
            $table->unsignedBigInteger('new_amount');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_user_id', 'created_at']);
        });

        Schema::table('kta_print_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('workflow_version')->default(1)->after('id_anggota_snapshot');
            $table->unsignedBigInteger('base_amount')->nullable()->after('payment_trx_id');
            $table->decimal('gateway_fee', 12, 2)->nullable()->after('base_amount');
        });

        DB::table('kta_print_requests')->whereNull('base_amount')->update([
            'base_amount' => DB::raw('COALESCE(payment_amount, '.(int) config('kta.print.amount', 25000).')'),
            'gateway_fee' => 0,
        ]);
    }

    public function down()
    {
        Schema::table('kta_print_requests', function (Blueprint $table) {
            $table->dropColumn(['workflow_version', 'base_amount', 'gateway_fee']);
        });

        Schema::dropIfExists('kta_price_histories');
        Schema::dropIfExists('kta_price_settings');
    }
};
