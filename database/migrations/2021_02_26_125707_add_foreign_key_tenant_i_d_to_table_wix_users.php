<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyTenantIDToTableWixUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_tenant_id');
            $table->foreign('fk_tenant_id')->references('id')->on('tenants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->dropForeign(['fk_tenant_id']);
            $table->dropColumn('fk_tenant_id');
        });
    }
}
