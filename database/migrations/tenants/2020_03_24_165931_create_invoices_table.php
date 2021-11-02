<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_order_id');
            $table->unsignedBigInteger('fk_invoice_status_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('fk_order_id')->references('id')->on('orders');
            $table->foreign('fk_invoice_status_id')->references('id')->on('invoice__statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
