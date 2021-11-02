<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableCategoryAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('fk_category_id');
            $table->string('name');
            $table->string('value')->nullable();

            //Fremdschlüssel
            $table->foreign('fk_category_id')->references('id')->on('categories');

            //index
            $table->index('name');
            $table->index(['name','value']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_category_attributes');
    }
}
