<?php

use Illuminate\Database\Seeder;
use App\Tenant\CommissionOrder_Status;

class CommissionOrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CommissionOrder_Status::create([
            'description' => 'In Bearbeitung'
        ]);

        CommissionOrder_Status::create([
            'description' => 'Abgeschlossen'
        ]);

    }
}
