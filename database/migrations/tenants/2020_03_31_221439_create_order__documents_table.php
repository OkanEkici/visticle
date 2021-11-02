<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order__documents', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_order_id');
            $table->unsignedBigInteger('fk_order_document_type_id');
        });

        Schema::table('order__documents', function (Blueprint $table) {
            $table->foreign('fk_order_id')->references('id')->on('orders');
            $table->foreign('fk_order_document_type_id')->references('id')->on('order__document__types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order__documents');
    }
}
