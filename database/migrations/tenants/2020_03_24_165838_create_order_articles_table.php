<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_id');
            $table->unsignedBigInteger('fk_article_variation_id')->nullable();
            $table->unsignedBigInteger('fk_order_id');
            $table->unsignedBigInteger('fk_orderarticle_status_id');
            $table->integer('quantity');
            $table->integer('price');
        });

        Schema::table('order_articles', function (Blueprint $table) {
            $table->foreign('fk_article_id')->references('id')->on('articles');
            $table->foreign('fk_article_variation_id')->references('id')->on('article__variations');
            $table->foreign('fk_order_id')->references('id')->on('orders');
            $table->foreign('fk_orderarticle_status_id')->references('id')->on('order_article__statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_articles');
    }
}
