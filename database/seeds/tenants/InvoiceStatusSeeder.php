<?php

use Illuminate\Database\Seeder;
use App\Tenant\Invoice_Status;

class InvoiceStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Invoice_Status::create([
            'description' => 'Offen'
        ]);

        Invoice_Status::create([
            'description' => 'Teilweise bezahlt'
        ]);

        Invoice_Status::create([
            'description' => 'Bezahlt'
        ]);
    }
}
