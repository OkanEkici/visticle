<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsIsFilterableAttributeGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attribute__groups', function (Blueprint $table) {
            $table->boolean('main_group')->nullable();
            $table->boolean('is_filterable')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attribute__groups', function (Blueprint $table) {
            $table->dropColumn('main_group');
            $table->dropColumn('is_filterable');
        });
    }
}
