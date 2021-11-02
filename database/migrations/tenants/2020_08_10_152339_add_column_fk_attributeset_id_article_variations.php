<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnFkAttributesetIdArticleVariations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article__variations', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_attributeset_id')->nullable();
            $table->foreign('fk_attributeset_id')->references('id')->on('attribute__sets');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article__variations', function (Blueprint $table) {
            $table->dropForeign(['fk_attributeset_id']);
            $table->dropColumn('fk_attributeset_id');
        });
    }
}
