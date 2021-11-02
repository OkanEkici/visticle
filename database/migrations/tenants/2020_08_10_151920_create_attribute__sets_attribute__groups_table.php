<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttributeSetsAttributeGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attribute__sets_attribute__groups', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_attributeset_id');
            $table->unsignedBigInteger('fk_attributegroup_id');
        });

        Schema::table('attribute__sets_attribute__groups', function (Blueprint $table) {
            $table->foreign('fk_attributeset_id')->references('id')->on('attribute__sets');
            $table->foreign('fk_attributegroup_id')->references('id')->on('attribute__groups');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attribute__sets_attribute__groups');
    }
}
