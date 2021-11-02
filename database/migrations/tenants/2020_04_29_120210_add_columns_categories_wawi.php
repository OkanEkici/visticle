<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsCategoriesWawi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_wawi_id')->nullable();
            $table->string('wawi_number')->nullable();
            $table->string('wawi_description')->nullable();
            $table->string('wawi_name')->nullable();

            $table->foreign('fk_wawi_id')->references('id')->on('wa_wis');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['fk_wawi_id']);
            $table->dropColumn('fk_wawi_id');
            $table->dropColumn('wawi_number');
            $table->dropColumn('wawi_description');
            $table->dropColumn('wawi_name');
        });
    }
}
