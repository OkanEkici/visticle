<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OnDeleteCascadeVariationImageAttr extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article__variation__image__attributes', function (Blueprint $table) {
            $table->dropForeign('fk_avar_imgatt_id');
            $table->foreign('fk_article_variation_image_id','fk_avar_imgatt_id')
            ->references('id')->on('article__variation__images')
            ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article__variation__image__attributes', function (Blueprint $table) {
            $table->dropForeign('fk_avar_imgatt_id');
            $table->foreign('fk_article_variation_image_id','fk_avar_imgatt_id')
            ->references('id')->on('article__variation__images');
        });
    }
}
