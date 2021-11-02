<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_providers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_provider_id');
            $table->unsignedBigInteger('fk_article_id');
            $table->unsignedBigInteger('fk_article_variation_id')->nullable();
            $table->boolean('active');
        });

        Schema::table('article_providers', function (Blueprint $table) {
            $table->foreign('fk_provider_id')->references('id')->on('providers');
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
        Schema::dropIfExists('article_providers');
    }
}
