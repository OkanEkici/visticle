<?php

use Illuminate\Database\Seeder;
use App\Tenant\Provider_Config_Payment;

class ProviderConfigPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Provider_Config_Payment::create([
            'fk_provider_config_id' => 1,
            'fk_payment_id' => 2,
            'active' => 1
        ]);
    }
}
