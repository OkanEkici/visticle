<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_provider_id')->nullable();
            $table->unsignedBigInteger('fk_order_status_id');
            $table->unsignedBigInteger('fk_config_shipment_id')->nullable();
            $table->unsignedBigInteger('fk_config_payment_id')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('fk_provider_id')->references('id')->on('providers');
            $table->foreign('fk_order_status_id')->references('id')->on('order__statuses');
            $table->foreign('fk_config_shipment_id', 'fk_opcs_id')->references('id')->on('config__shipments');
            $table->foreign('fk_config_payment_id', 'fk_opcp_id')->references('id')->on('config__payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
