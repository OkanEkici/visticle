<?php

use App\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('subdomain');
            $table->string('name');
            $table->string('db');
            $table->string('db_user');
            $table->string('db_pw');
            $table->boolean('is_fee_customer')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $dynamic = env('TENANT_DB_CREATION_DYNAMIC', 'false');
        if($dynamic == 'true'){
            $tenants = Tenant::all();
            $tenants->each(function($tenant){
                \DB::connection('tenant')->statement('DROP DATABASE IF EXISTS '.$tenant->db);
            });
        }
        Schema::dropIfExists('tenants');
    }
}
