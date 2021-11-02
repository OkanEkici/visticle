<?php

use Illuminate\Database\Seeder;
use App\Tenant\Config_Shipment_Attribute;

class ConfigShipmentAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Config_Shipment_Attribute::create([
            'fk_shipment_id' => 1,
            'name' => 'duration',
            'value' => '3-5 Werktage'
        ]);
    }
}
