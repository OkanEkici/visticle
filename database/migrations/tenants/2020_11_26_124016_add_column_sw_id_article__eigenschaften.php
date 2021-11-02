<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnSwIdArticleEigenschaften extends Migration
{
    public function up()
    {
        Schema::table('article__eigenschaften', function (Blueprint $table) {
            $table->integer('sw_id')->nullable();
        });
    }
    public function down()
    {
        Schema::table('article__eigenschaften', function (Blueprint $table) {
            $table->dropColumn('sw_id');
        });
    }
}
