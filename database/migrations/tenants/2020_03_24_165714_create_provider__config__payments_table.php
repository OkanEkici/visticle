<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProviderConfigPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provider__config__payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_provider_config_id');
            $table->unsignedBigInteger('fk_payment_id');
            $table->boolean('active');
        });

        Schema::table('provider__config__payments', function (Blueprint $table) {
            $table->foreign('fk_provider_config_id')->references('id')->on('provider__configs');
            $table->foreign('fk_payment_id')->references('id')->on('config__payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('provider__config__payments');
    }
}
