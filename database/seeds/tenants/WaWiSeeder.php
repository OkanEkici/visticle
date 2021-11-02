<?php

use App\Tenant\WaWi;
use Illuminate\Database\Seeder;

class WaWiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        WaWi::create([
            'name' => 'FEE'
        ]);
    }
}
