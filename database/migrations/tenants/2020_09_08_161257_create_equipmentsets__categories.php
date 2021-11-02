<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEquipmentsetsCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('equipmentsets__categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_eqset_id');
            $table->unsignedBigInteger('fk_category_id');
        });
        
        Schema::table('equipmentsets__categories', function (Blueprint $table) {
            $table->foreign('fk_eqset_id')->references('id')->on('equipmentsets');
            $table->foreign('fk_category_id')->references('id')->on('categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipmentsets__categories');
    }
}
