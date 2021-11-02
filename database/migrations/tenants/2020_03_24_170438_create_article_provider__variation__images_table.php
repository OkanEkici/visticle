<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleProviderVariationImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_provider__variation__images', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_articleprovider_id');
            $table->string('location');
            $table->boolean('active');
        });

        Schema::table('article_provider__variation__images', function (Blueprint $table) {
            $table->foreign('fk_articleprovider_id','fk_api_id')->references('id')->on('article_providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article_provider__variation__images');
    }
}
