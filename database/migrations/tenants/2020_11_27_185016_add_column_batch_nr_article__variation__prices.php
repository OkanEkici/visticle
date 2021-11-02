<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBatchNrArticleVariationPrices extends Migration
{
    public function up()
    {
        Schema::table('article__variation__prices', function (Blueprint $table) {
            $table->string('batch_nr')->nullable();
        });
    }
    public function down()
    {
        Schema::table('article__variation__prices', function (Blueprint $table) {
            $table->dropColumn('batch_nr');
        });
    }
}
