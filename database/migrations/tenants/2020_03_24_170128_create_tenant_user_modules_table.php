<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantUserModulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenant_user_modules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_module_id');
            $table->boolean('active');
        });

        Schema::table('tenant_user_modules', function (Blueprint $table) {
            $table->foreign('fk_user_id')->references('id')->on('tenant_users');
            $table->foreign('fk_module_id')->references('id')->on('modules');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_user_modules');
    }
}
