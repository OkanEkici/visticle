<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnExtraEanArticleVariations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article__variations', function (Blueprint $table) {
            $table->string('extra_ean')->nullable();
            $table->integer('min_stock')->nullable();
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
            $table->dropColumn('extra_ean');
            $table->dropColumn('min_stock');
        });
    }
}
