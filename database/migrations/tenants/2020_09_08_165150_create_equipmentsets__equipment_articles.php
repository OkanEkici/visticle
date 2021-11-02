<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEquipmentsetsEquipmentArticles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('equipmentsets__equipment_articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_eqset_id');
            $table->unsignedBigInteger('fk_article_id');
            $table->unsignedBigInteger('fk_art_var_id')->nullable();
        });
        
        Schema::table('equipmentsets__equipment_articles', function (Blueprint $table) {
            $table->foreign('fk_eqset_id')->references('id')->on('equipmentsets');
            $table->foreign('fk_article_id')->references('id')->on('articles');
            $table->foreign('fk_art_var_id')->references('id')->on('article__variations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipmentsets__equipment_articles');
    }
}
