<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsActiveAttributeGroups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attribute__groups', function (Blueprint $table) {
            $table->string('unit_type')->nullable();
			$table->boolean('active')->nullable();
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
            $table->dropColumn('unit_type');
			$table->dropColumn('active');
        });
    }
}
