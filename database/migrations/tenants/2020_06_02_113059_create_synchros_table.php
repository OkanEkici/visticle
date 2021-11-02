<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSynchrosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('synchros', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fk_synchro_type_id');
            $table->unsignedBigInteger('fk_synchro_status_id');
            $table->integer('expected_count')->nullable();
            $table->integer('success_count')->nullable();
            $table->integer('failed_count')->nullable();
            $table->string('filepath')->nullable();
            $table->string('url')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('add_data')->nullable();
        });

        Schema::table('synchros', function (Blueprint $table) {
            $table->foreign('fk_synchro_type_id')->references('id')->on('synchro__types');
            $table->foreign('fk_synchro_status_id')->references('id')->on('synchro__statuses');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('synchros');
    }
}
