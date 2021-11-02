<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerAttributesTable extends Migration
{
    public function up()
    {
        Schema::create('customer__attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_customer_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('customer__attributes', function (Blueprint $table) {
            $table->foreign('fk_customer_id')->references('id')->on('customers');
        });
    }
    public function down()
    {
        Schema::dropIfExists('customer__attributes');
    }
}
