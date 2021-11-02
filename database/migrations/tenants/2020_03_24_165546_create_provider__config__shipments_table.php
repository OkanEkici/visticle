<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProviderConfigShipmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provider__config__shipments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_provider_config_id');
            $table->unsignedBigInteger('fk_shipment_id');
            $table->boolean('active');
        });

        Schema::table('provider__config__shipments', function (Blueprint $table) {
            $table->foreign('fk_provider_config_id')->references('id')->on('provider__configs');
            $table->foreign('fk_shipment_id')->references('id')->on('config__shipments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('provider__config__shipments');
    }
}
