<?php

use Illuminate\Database\Seeder;
use App\Tenant\Synchro_Type;

class SynchroTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Synchro_Type::create([
            'key' => 'fee_import_csv',
            'name' => 'FEE CSV Artikelimport',
            'description' => 'Artikelimport von FEE über CSV Datei'
        ]);

        Synchro_Type::create([
            'key' => 'fashioncloud_update',
            'name' => 'Fashion Cloud Update',
            'description' => 'Update von Fashioncloudinhalten'
        ]);

        Synchro_Type::create([
            'key' => 'shop_content_update',
            'name' => 'VS-Shop Content Update',
            'description' => 'Update von Shopinhalten'
        ]);

        Synchro_Type::create([
            'key' => 'shop_stock_update',
            'name' => 'VS-Shop Bestandsupdate',
            'description' => 'Update von Beständen im Shop'
        ]);

        Synchro_Type::create([
            'key' => 'fee_stock_update',
            'name' => 'FEE Bestandsupdate',
            'description' => 'Update von Beständen durch FEE-Import'
        ]);

        Synchro_Type::create([
            'key' => 'zalando_csv_export',
            'name' => 'Zalando CSV Export',
            'description' => 'Export von CSV an Zalando Connected Retail'
        ]);
    }
}
