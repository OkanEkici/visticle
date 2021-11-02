<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleEigenschaftenTable extends Migration
{
    public function up()
    {
        Schema::create('article__eigenschaften', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');		
			$table->boolean('is_filterable')->nullable();
			$table->boolean('active');
        });
    }
    public function down()
    {
        Schema::dropIfExists('article__eigenschaften');
    }
}
