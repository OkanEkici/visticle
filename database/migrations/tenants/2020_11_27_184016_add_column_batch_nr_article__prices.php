<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBatchNrArticlePrices extends Migration
{
    public function up()
    {
        Schema::table('article__prices', function (Blueprint $table) {
            $table->string('batch_nr')->nullable();
        });
    }
    public function down()
    {
        Schema::table('article__prices', function (Blueprint $table) {
            $table->dropColumn('batch_nr');
        });
    }
}
