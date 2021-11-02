<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumsCustomers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('UStId')->nullable();
            $table->string('zusatz_telefon')->nullable();
            $table->string('zusatz_email')->nullable();
            $table->mediumText('text_info')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('UStId');
            $table->dropColumn('zusatz_telefon');
            $table->dropColumn('zusatz_email');
            $table->dropColumn('text_info');
        });
    }
}
