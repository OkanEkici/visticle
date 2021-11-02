<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryProviderImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_provider__images', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_categoryprovider_id');
            $table->string('location');
            $table->boolean('loaded');
            $table->boolean('is_thumbnail');
            $table->boolean('is_banner');
        });

        Schema::table('category_provider__images', function (Blueprint $table) {
            $table->foreign('fk_categoryprovider_id')->references('id')->on('category_providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('category_provider__images');
    }
}
