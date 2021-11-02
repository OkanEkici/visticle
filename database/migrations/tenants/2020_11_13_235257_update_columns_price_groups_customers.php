<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateColumnsPriceGroupsCustomers extends Migration
{
    public function up()
    {
        Schema::table('price__groups__customers', function (Blueprint $table) {
			$table->string('rel_type'); // percent || solid
            $table->string('rel_value');
			$table->string('standard')->nullable()->change();
			$table->string('discount')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('price__groups__customers', function (Blueprint $table) {
			$table->dropColumn('rel_type');
			$table->dropColumn('rel_value');
			$table->float('standard')->change();
			$table->float('discount')->change();
        });
    }
}
