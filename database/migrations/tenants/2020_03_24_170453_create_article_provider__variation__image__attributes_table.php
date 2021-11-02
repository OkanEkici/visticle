<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleProviderVariationImageAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_provider__variation__image__attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_articleprovider_variation_image_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('article_provider__variation__image__attributes', function (Blueprint $table) {
            $table->foreign('fk_articleprovider_variation_image_id', 'fk_apvimg_id')->references('id')->on('article_provider__variation__images');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article_provider__variation__image__attributes');
    }
}
