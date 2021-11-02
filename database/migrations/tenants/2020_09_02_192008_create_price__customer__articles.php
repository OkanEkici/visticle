<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceCustomerArticles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price__customer__articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            
            $table->string('rel_type'); // percent || individual (preise werden individuell gespeichert)
            $table->float('rel_value');
            $table->float('standard');
            $table->float('discount');
            $table->unsignedBigInteger('fk_customer_id');
            $table->unsignedBigInteger('fk_article_id')->nullable();
            $table->unsignedBigInteger('fk_article_variation_id')->nullable();
        });

        
        Schema::table('price__customer__articles', function (Blueprint $table) {
            $table->foreign('fk_customer_id')->references('id')->on('customers');
            $table->foreign('fk_article_id')->references('id')->on('articles');
            $table->foreign('fk_article_variation_id')->references('id')->on('article__variations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price__customer__articles');
    }
}
