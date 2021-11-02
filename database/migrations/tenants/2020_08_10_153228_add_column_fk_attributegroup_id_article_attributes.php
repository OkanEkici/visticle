<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnFkAttributegroupIdArticleAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article__attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_attributegroup_id')->nullable();
            $table->foreign('fk_attributegroup_id')->references('id')->on('attribute__groups');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article__attributes', function (Blueprint $table) {
            $table->dropForeign(['fk_attributegroup_id']);
            $table->dropColumn('fk_attributegroup_id');
        });
    }
}
