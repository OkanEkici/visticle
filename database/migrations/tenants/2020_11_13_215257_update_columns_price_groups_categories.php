<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateColumnsPriceGroupsCategories extends Migration
{
    public function up()
    {
        Schema::table('price__customer__categories', function (Blueprint $table) {
			$table->string('rel_value')->change();
            //DB::statement('ALTER TABLE mytable MODIFY mycolumn  LONGTEXT;');
        });
    }

    public function down()
    {
        Schema::table('price__customer__categories', function (Blueprint $table) {
			$table->float('rel_value')->change();
            //DB::statement('ALTER TABLE mytable MODIFY mycolumn TEXT;');
        });
    }
}
