<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->unsignedBigInteger('fk_voucher_id');
            $table->unsignedBigInteger('fk_order_id');
            $table->string('code');
            $table->string('for'); // Global / category / article
			$table->string('type');
			$table->float('value');
            $table->float('cart_price_limit');
        });

        Schema::table('order_vouchers', function (Blueprint $table) {
            $table->foreign('fk_voucher_id')->references('id')->on('vouchers');
        });

        Schema::table('order_vouchers', function (Blueprint $table) {
            $table->foreign('fk_order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_vouchers');
    }
}
