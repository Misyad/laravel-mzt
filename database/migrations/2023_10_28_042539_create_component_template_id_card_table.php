<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('component_template_id_card', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_template');
            $table->foreign('id_template')->references('id')->on('template_id_card');
            $table->string('title');
            $table->text('position_x');
            $table->text('position_y');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('component_template_id_card');
    }
};
