<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceCorrections extends Migration
{
    public function up()
    {
        Schema::create('invoice_corrections', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_order_id');
            $table->integer('number')->nullable();
        });

        Schema::table('invoice_corrections', function (Blueprint $table) {
            $table->foreign('fk_order_id')->references('id')->on('orders');

        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_corrections');
    }
}
