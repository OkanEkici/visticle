<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSparesetsArticles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sparesets__articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();    
            $table->unsignedBigInteger('fk_spareset_id');
            $table->unsignedBigInteger('fk_article_id');
            $table->unsignedBigInteger('fk_art_var_id')->nullable();
        });
        
        Schema::table('sparesets__articles', function (Blueprint $table) {
            $table->foreign('fk_spareset_id')->references('id')->on('sparesets');
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
        Schema::dropIfExists('sparesets__articles');
    }
}
