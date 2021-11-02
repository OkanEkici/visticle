<?php

use App\Tenant_Keys;
use Illuminate\Database\Seeder;

class TenantKeysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Tenant_Keys::create([
            'fk_tenant_id' => 3,
            'provider_id' => 1,
            'access_key' => 'test123',
            'active' => 1 
        ]);
    }
}
