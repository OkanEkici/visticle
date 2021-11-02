<?php

use App\AccountType;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AccountType::create([
            'description' => 'Visitlce Textile',
            'type_key' => 'vstcl-textile'
        ]);

        AccountType::create([
            'description' => 'Visitlce Industry',
            'type_key' => 'vstcl-industry'
        ]);
    }
}
