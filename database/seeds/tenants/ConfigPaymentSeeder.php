<?php

use Illuminate\Database\Seeder;
use App\Tenant\Config_Payment;

class ConfigPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Config_Payment::create([
            'payment_key' => 'PayPal',
            'active' => 1,
        ]);

        Config_Payment::create([
            'payment_key' => 'Vorkasse',
            'active' => 1,
        ]);

        Config_Payment::create([
            'payment_key' => 'Bezahlung auf Rechnung',
            'active' => 1,
        ]);
    }
}
