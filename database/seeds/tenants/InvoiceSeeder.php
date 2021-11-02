<?php

use Illuminate\Database\Seeder;
use App\Tenant\Invoice;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        for($i = 1; $i < 100; $i++) {
        Invoice::create([
            'fk_order_id' => $i,
            'fk_invoice_status_id' => 1,
            'number' => $i + 1
        ]);
        }
    }
}
