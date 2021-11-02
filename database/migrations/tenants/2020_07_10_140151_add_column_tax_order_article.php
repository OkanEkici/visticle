<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTaxOrderArticle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_articles', function (Blueprint $table) {
            //$table->decimal('tax', 5, 2)->default('16.00');
            $table->decimal('tax', 5, 2)->default(\App\Helpers\VAT::getVAT());//04.01.2021
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_articles', function (Blueprint $table) {
            $table->dropColumn('tax');
        });
    }
}
