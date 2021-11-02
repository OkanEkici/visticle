<?php

use Illuminate\Database\Seeder;
use App\Tenant\Provider_Config;

class ProviderConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Provider_Config::create([
            'fk_provider_id' => 1
        ]);
    }
}
