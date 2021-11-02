<?php

use Illuminate\Database\Seeder;
use App\Tenant\Order_Document_Type;

class OrderDocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Order_Document_Type::create([
            'name' => 'lieferschein',
            'description' => 'Lieferschein für eine Bestellung'
        ]);

        Order_Document_Type::create([
            'name' => 'packzettel',
            'description' => 'Der Packzettel zu einer Bestellung'
        ]);

        Order_Document_Type::create([
            'name' => 'rechnung',
            'description' => 'Die Rechnung für eine Bestellung'
        ]);

        Order_Document_Type::create([
            'name' => 'retoure',
            'description' => 'Retoureschein für eine Bestellung'
        ]);
    }
}
