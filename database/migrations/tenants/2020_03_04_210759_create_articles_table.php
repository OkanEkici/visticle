<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_brand_id')->nullable();
            $table->unsignedBigInteger('fk_wawi_id')->nullable();
            $table->string('vstcl_identifier');
            $table->string('ean');
            $table->string('sku')->nullable();
            $table->string('name')->nullable();
            $table->string('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('slug')->nullable();
            $table->integer('min_stock')->nullable();
            $table->boolean('active');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreign('fk_brand_id')->references('id')->on('brands')->onDelete('set null');
            $table->foreign('fk_wawi_id')->references('id')->on('wa_wis')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
