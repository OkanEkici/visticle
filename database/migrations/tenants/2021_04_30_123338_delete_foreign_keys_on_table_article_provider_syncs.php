<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DeleteForeignKeysOnTableArticleProviderSyncs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article_provider_syncs', function (Blueprint $table) {
            $table->dropForeign('article_provider_syncs_fk_article_id_foreign');
            $table->dropForeign('article_provider_syncs_fk_provider_id_foreign');



        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article_provider_syncs', function (Blueprint $table) {
            Schema::enableForeignKeyConstraints();


            $table->foreign('fk_article_id')->references('id')->on('articles');
            $table->foreign('fk_provider_id')->references('id')->on('providers');
        });
    }
}
