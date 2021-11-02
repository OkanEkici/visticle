<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchArticleVariationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branch_article__variations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_branch_id');
            $table->unsignedBigInteger('fk_article_variation_id');
            $table->integer('stock');
        });

        Schema::table('branch_article__variations', function (Blueprint $table) {
            $table->foreign('fk_branch_id')->references('id')->on('branches');
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
        Schema::dropIfExists('branch_article__variations');
    }
}
