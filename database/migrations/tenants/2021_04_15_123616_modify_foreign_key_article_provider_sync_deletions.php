<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyForeignKeyArticleProviderSyncDeletions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('article_provider_sync_deletions', function (Blueprint $table) {
            Schema::enableForeignKeyConstraints();

            $table->dropForeign('article_provider_sync_deletions_fk_sync_id_foreign');

             //Fremdschlüssel anlegen
             $table->foreign('fk_sync_id')->references('id')->on('article_provider_syncs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('article_provider_sync_deletions', function (Blueprint $table) {
            Schema::enableForeignKeyConstraints();
            $table->dropForeign('article_provider_sync_deletions_fk_sync_id_foreign');

            $table->foreign('fk_sync_id')->references('id')->on('article_provider_syncs');
        });
    }
}
