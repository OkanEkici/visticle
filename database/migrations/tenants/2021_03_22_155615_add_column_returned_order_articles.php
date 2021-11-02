<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnReturnedOrderArticles extends Migration
{
    public function up()
    {
        Schema::table('order_articles', function (Blueprint $table) {
            $table->integer('returned')->nullable();
        });
    }
    public function down()
    {
        Schema::table('order_articles', function (Blueprint $table) {
            $table->dropColumn('returned');
        });
    }
}
