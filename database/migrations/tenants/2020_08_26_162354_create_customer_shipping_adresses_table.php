<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerShippingAdressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer__shipping__adresses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_customer_id');
            
            $table->string('anrede')->nullable();
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->string('strasse_nr')->nullable();
            $table->string('plz')->nullable();
			$table->string('ort')->nullable();
            $table->string('region')->nullable();
            $table->string('telefon')->nullable();            
            $table->string('email')->nullable();

            $table->string('mobil')->nullable();
            $table->string('fax')->nullable();
        });

        Schema::table('customer__shipping__adresses', function (Blueprint $table) {
            $table->foreign('fk_customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_shipping_adresses');
    }
}
