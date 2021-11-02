<?php

use Illuminate\Database\Seeder;
use App\Tenant\Order;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 0; $i < 100; $i++){
            Order::create([
                'fk_order_status_id' => rand(1, 10),
                'fk_provider_id' => rand(1, 18),
                'fk_config_payment_id' => rand(1, 3),
                'fk_config_shipment_id' => 1,
                'number' => $i + 1
            ]);
        }
        
    }
}
