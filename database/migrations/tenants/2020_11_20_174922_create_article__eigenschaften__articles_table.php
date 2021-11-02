<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleEigenschaftenArticlesTable extends Migration
{
    public function up()
    {
        Schema::create('article__eigenschaften__articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_id');
			$table->unsignedBigInteger('fk_variation_id')->nullable();
			$table->unsignedBigInteger('fk_eigenschaft_data_id');
			$table->boolean('active');
        });

        Schema::table('article__eigenschaften__articles', function (Blueprint $table) {
            $table->foreign('fk_article_id')->references('id')->on('articles');
			$table->foreign('fk_variation_id')->references('id')->on('article__variations');
			$table->foreign('fk_eigenschaft_data_id')->references('id')->on('article__eigenschaften__data');
        });
    }
    public function down()
    {
        Schema::dropIfExists('article__eigenschaften__articles');
    }
}
