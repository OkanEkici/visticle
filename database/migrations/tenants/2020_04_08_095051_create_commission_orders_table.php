<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commission_orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_commission_id');
            $table->unsignedBigInteger('fk_order_id');
            $table->unsignedBigInteger('fk_commissionorder_status_id');
        });

        Schema::table('commission_orders', function(Blueprint $table) {
            $table->foreign('fk_commission_id')->references('id')->on('commissions');
            $table->foreign('fk_order_id')->references('id')->on('orders');
            $table->foreign('fk_commissionorder_status_id')->references('id')->on('commission_order__statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('commission_orders');
    }
}
