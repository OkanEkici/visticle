<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProviderConfigShipmentAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provider__config__shipment__attributes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_provider_config_shipment_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('provider__config__shipment__attributes', function (Blueprint $table) {
            $table->foreign('fk_provider_config_shipment_id', 'fk_pcship_id')->references('id')->on('provider__config__shipments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('provider__config__shipment__attributes');
    }
}
