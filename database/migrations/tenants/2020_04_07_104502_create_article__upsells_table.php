<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleUpsellsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article__upsells', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_main_article_id');
            $table->unsignedBigInteger('fk_upsell_article_id')->nullable();
            $table->unsignedBigInteger('fk_upsell_category_id')->nullable();
        });

        Schema::table('article__upsells', function (Blueprint $table) {
            $table->foreign('fk_main_article_id')->references('id')->on('articles');
            $table->foreign('fk_upsell_article_id')->references('id')->on('articles');
            $table->foreign('fk_upsell_category_id')->references('id')->on('categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article__upsells');
    }
}
