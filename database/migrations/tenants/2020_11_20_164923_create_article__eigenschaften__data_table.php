<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleEigenschaftenDataTable extends Migration
{
    public function up()
    {
        Schema::create('article__eigenschaften__data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
			$table->unsignedBigInteger('fk_eigenschaft_id');
            $table->string('name');
            $table->string('value');
        });

        Schema::table('article__eigenschaften__data', function (Blueprint $table) {
            $table->foreign('fk_eigenschaft_id')->references('id')->on('article__eigenschaften');
        });
    }
    public function down()
    {
        Schema::dropIfExists('article__eigenschaften__data');
    }
}
