<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleVariationImageAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article__variation__image__attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_variation_image_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('article__variation__image__attributes', function (Blueprint $table) {
            $table->foreign('fk_article_variation_image_id','fk_avar_imgatt_id')->references('id')->on('article__variation__images');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article__variation__image__attributes');
    }
}
