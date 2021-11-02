<?php

use Illuminate\Database\Seeder;
use App\Tenant\Settings_Type;

class SettingsTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Settings_Type::create([
            'name' => 'general',
            'description' => 'Stammdaten'
        ]);

        Settings_Type::create([
            'name' => 'receipt',
            'description' => 'Belegeinstellungen'
        ]);

        Settings_Type::create([
            'name' => 'payment',
            'description' => 'Zahlungsarten'
        ]);

        Settings_Type::create([
            'name' => 'shipping',
            'description' => 'Versandarten'
        ]);

        Settings_Type::create([
            'name' => 'partner',
            'description' => 'Partnereinstellungen'
        ]);
    }
}
