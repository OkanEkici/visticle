<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIdvKnrCustomers extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('idv_knr')->nullable();
        });
    }
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('idv_knr');
        });
    }
}
