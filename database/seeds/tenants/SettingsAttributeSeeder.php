<?php

use Illuminate\Database\Seeder;
use App\Tenant\Settings_Attribute;

class SettingsAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $numberKeys = ['invoice', 'credit', 'delivery_note', 'commission', 'packing', 'order'];

        //Default NumberRanges
        foreach ($numberKeys as $numberKey) {
            Settings_Attribute::create([
                'fk_setting_id' => 1,
                'name' => 'number_'.$numberKey,
                'value' => '1000000'
            ]);
        }
    }
}
