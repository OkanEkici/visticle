<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableArticleProviderSyncs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_provider_syncs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('fk_article_id');
            $table->unsignedBigInteger('fk_provider_id');
            $table->string('operation',50);
            $table->string('subject',255)->nullable(true);
            $table->unsignedBigInteger('subject_id')->nullable(true);
            $table->string('context',255)->nullable();
            $table->text('context_value')->nullable();
            $table->tinyInteger('priority');

            //Fremdschlüssel anlegen!
            $table->foreign('fk_article_id')->references('id')->on('articles');
            $table->foreign('fk_provider_id')->references('id')->on('providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('article_provider_syncs');
    }
}
