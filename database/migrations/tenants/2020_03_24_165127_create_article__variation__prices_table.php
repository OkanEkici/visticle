<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleVariationPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article__variation__prices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_variation_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('article__variation__prices', function (Blueprint $table) {
            $table->foreign('fk_article_variation_id','fk_avar_price_id')->references('id')->on('article__variations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article__variation__prices');
    }
}
