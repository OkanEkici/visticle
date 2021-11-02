<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleImageAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article__image__attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_article_image_id');
            $table->string('name');
            $table->string('value')->nullable();
        });

        Schema::table('article__image__attributes', function (Blueprint $table) {
            $table->foreign('fk_article_image_id','fk_a_imgatt_id')->references('id')->on('article__images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article__image__attributes');
    }
}
