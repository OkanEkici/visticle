<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsBrandsuppliers extends Migration
{
    public function up()
    {
        Schema::table('brands_suppliers', function (Blueprint $table) {
			$table->string('hersteller_name')->nullable();
        });
    }

    public function down()
    {
        Schema::table('brands_suppliers', function (Blueprint $table) {
			$table->dropColumn('hersteller_name');
        });
    }
}
