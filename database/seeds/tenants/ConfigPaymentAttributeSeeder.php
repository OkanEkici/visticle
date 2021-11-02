<?php

use Illuminate\Database\Seeder;
use App\Tenant\Config_Payment_Attribute;

class ConfigPaymentAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Config_Payment_Attribute::create([
            'fk_payment_id' => 1,
            'name' => 'api_key',
            'value' => 'test123456789'
        ]);
    }
}
