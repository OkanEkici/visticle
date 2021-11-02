<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleMarketingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article__marketings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_id');
            $table->string('name');
            $table->dateTime('from')->nullable();
            $table->dateTime('until')->nullable();
            $table->boolean('active');
        });

        Schema::table('article__marketings', function (Blueprint $table) {
            $table->foreign('fk_article_id')->references('id')->on('articles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article__marketings');
    }
}
