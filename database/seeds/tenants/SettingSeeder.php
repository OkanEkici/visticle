<?php

use Illuminate\Database\Seeder;
use App\Tenant\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 1; $i < 6; $i ++)
        Setting::create([
            'fk_settings_type_id' => $i
        ]);
    }
}
