<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentConditionsCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_conditions_customers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_pcondition_id');
            $table->unsignedBigInteger('fk_customer_id');
        });
        Schema::table('payment_conditions_customers', function (Blueprint $table) {
            $table->foreign('fk_pcondition_id')->references('id')->on('payment_conditions');
            $table->foreign('fk_customer_id')->references('id')->on('customers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_conditions_customers');
    }
}
