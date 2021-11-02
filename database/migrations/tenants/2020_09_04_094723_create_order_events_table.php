<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('event_id');
            $table->text('order_id');
            $table->text('order_number');
            $table->string('state');
            $table->string('last_state')->nullable();
            $table->string('store_id')->nullable();
            $table->date('timestamp');
            $table->text('items')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_events');
    }
}
