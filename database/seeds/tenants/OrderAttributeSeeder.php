<?php

use Illuminate\Database\Seeder;
use App\Tenant\Order_Attribute;

class OrderAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $shippingAddrKeys = ['shippingaddress_', 'billingaddress_'];
        for($i = 1; $i < 99; $i++) {
            $orderId = $i;
            foreach($shippingAddrKeys as $shippingAddrKey) {
                if($i % 2 != 0 && $shippingAddrKey == 'shippingaddress_') {
                    Order_Attribute::create([
                        'fk_order_id' => $orderId,
                        'name' => 'billingasshipping',
                        'value' => 'true'
                    ]);
                    continue;
                }
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'gender',
                    'value' => 'm'
                ]);

                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'vorname',
                    'value' => 'Max'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'nachname',
                    'value' => 'Mustermann'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'street',
                    'value' => 'Musterstraße 123a'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'postcode',
                    'value' => '12345'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'city',
                    'value' => 'Berlin'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'region',
                    'value' => 'DE'
                ]);
    
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'tel',
                    'value' => '+49 12345 6789'
                ]);
    
                
                Order_Attribute::create([
                    'fk_order_id' => $orderId,
                    'name' => $shippingAddrKey.'email',
                    'value' => 'mustermann@example.com'
                ]);
            }
        }
    }
}
