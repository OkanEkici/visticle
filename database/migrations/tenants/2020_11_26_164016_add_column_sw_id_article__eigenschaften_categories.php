<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnSwIdArticleEigenschaftenCategories extends Migration
{
    public function up()
    {
        Schema::table('article__eigenschaften__categories', function (Blueprint $table) {
            $table->integer('sw_id')->nullable();
        });
    }
    public function down()
    {
        Schema::table('article__eigenschaften__categories', function (Blueprint $table) {
            $table->dropColumn('sw_id');
        });
    }
}
