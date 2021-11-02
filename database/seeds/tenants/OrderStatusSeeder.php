<?php

use Illuminate\Database\Seeder;
use App\Tenant\Order_Status;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Order_Status::create([
            'key' => 'open',
            'description' => 'Offen'
        ]);

        Order_Status::create([
            'key' => 'awaiting_payment',
            'description' => 'Bezahlung ausstehend'
        ]);

        Order_Status::create([
            'key' => 'awaiting_fulfillment',
            'description' => 'Bearbeitung ausstehend'
        ]);

        Order_Status::create([
            'key' => 'awaiting_shipment',
            'description' => 'Lieferung ausstehend'
        ]);

        Order_Status::create([
            'key' => 'cancelled',
            'description' => 'Storniert'
        ]);

        Order_Status::create([
            'key' => 'declined',
            'description' => 'Abgelehnt'
        ]);

        Order_Status::create([
            'key' => 'shipped',
            'description' => 'Geliefert'
        ]);

        Order_Status::create([
            'key' => 'partially_shipped',
            'description' => 'Teilweise geliefert'
        ]);

        Order_Status::create([
            'key' => 'refunded',
            'description' => 'Erstattet'
        ]);

        Order_Status::create([
            'key' => 'completed',
            'description' => 'Abgeschlossen'
        ]);
    }
}
