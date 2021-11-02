<?php

use Illuminate\Database\Seeder;
use App\Tenant\Config_Shipment;

class ConfigShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Config_Shipment::create([
            'shipment_key' => 'Standard DHL',
            'active' => 1,
        ]);

        Config_Shipment::create([
            'shipment_key' => 'Abholung',
            'active' => 1,
        ]);
    }
}
