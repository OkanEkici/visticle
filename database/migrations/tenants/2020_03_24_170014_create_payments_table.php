<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_invoice_id');
            $table->unsignedBigInteger('fk_config_payment_id')->nullable();
            $table->dateTime('payment_date');
            $table->float('payment_amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('fk_invoice_id')->references('id')->on('invoices');
            $table->foreign('fk_config_payment_id', 'fk_ppcp_id')->references('id')->on('config__payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
