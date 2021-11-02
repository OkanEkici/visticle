<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsPriceGroupsCategories extends Migration
{
    public function up()
    {
        Schema::table('price__customer__categories', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_pricegroup_id')->nullable();
            $table->foreign('fk_pricegroup_id')->references('id')->on('price__groups')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('price__customer__categories', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_pricegroup_id')->unsigned();
			$table->foreign('fk_pricegroup_id')->references('id')->on('price__groups');
        });
    }
}
