<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceCustomerCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price__customer__categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            
            $table->string('rel_type'); // percent || solid
            $table->float('rel_value');
            $table->unsignedBigInteger('fk_customer_id');
            $table->unsignedBigInteger('fk_category_id');
        });

        
        Schema::table('price__customer__categories', function (Blueprint $table) {
            $table->foreign('fk_customer_id')->references('id')->on('customers');
            $table->foreign('fk_category_id')->references('id')->on('categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price__customer__categories');
    }
}
