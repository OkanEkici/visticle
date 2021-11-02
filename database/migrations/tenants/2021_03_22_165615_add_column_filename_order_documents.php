<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnFilenameOrderDocuments extends Migration
{
    public function up()
    {
        Schema::table('order__documents', function (Blueprint $table) {
            $table->string('filename')->nullable();
        });
    }
    public function down()
    {
        Schema::table('order__documents', function (Blueprint $table) {
            $table->dropColumn('filename');
        });
    }
}
