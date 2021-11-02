<?php

use App\Tenant;
use App\Tenant_Keys;
use Illuminate\Database\Seeder;
use App\Jobs\TenantDatabase;
use App\Services\TenantManager;

class TenantTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $tenants = [];

        $tenants[] = Tenant::create([
            'user_id' => 1,
            'subdomain' => 'demo1',
            'name' => 'VISC Media Demo1',
            'db' => env('TEST_TENANT_DB', 'tenant_1'),
            'db_user' => env('TEST_TENANT_DB_USER', 'root'),
            'db_pw' => encrypt(env('TEST_TENANT_DB_PW', 'root')),
            'is_fee_customer' => true,
        ]);

        $tenants[] = Tenant::create([
            'user_id' => 1,
            'subdomain' => 'demo2',
            'name' => 'VISC Media Demo2',
            'db' => env('TEST_TENANT_DB2', 'tenant_2'),
            'db_user' => env('TEST_TENANT_DB_USER2', 'root'),
            'db_pw' => encrypt(env('TEST_TENANT_DB_PW2', 'root')),
            'is_fee_customer' => true,
        ]);

        $tenants[] = Tenant::create([
            'user_id' => 1,
            'subdomain' => 'demo3',
            'name' => 'VISC Media Demo3',
            'db' => env('TEST_TENANT_DB3', 'tenant_3'),
            'db_user' => env('TEST_TENANT_DB_USER3', 'root'),
            'db_pw' => encrypt(env('TEST_TENANT_DB_PW3', 'root')),
            'is_fee_customer' => true,
        ]);

        Tenant_Keys::create([
            'fk_tenant_id' => 3,
            'provider_id' => 1,
            'access_key' => 'test123',
            'active' => 1 
        ]);


        collect($tenants)->each(function($tenant) {
            App\Jobs\TenantDatabase::dispatch($tenant, app(App\Services\TenantManager::class));
        });

    }
}
