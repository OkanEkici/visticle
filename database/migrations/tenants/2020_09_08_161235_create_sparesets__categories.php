<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSparesetsCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sparesets__categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_spareset_id');
            $table->unsignedBigInteger('fk_category_id');
        });
        
        Schema::table('sparesets__categories', function (Blueprint $table) {
            $table->foreign('fk_spareset_id')->references('id')->on('sparesets');
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
        Schema::dropIfExists('sparesets__categories');
    }
}
