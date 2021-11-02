<?php

use Illuminate\Database\Seeder;
use App\Tenant\Article_Shipment;

class ArticleShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Article_Shipment::create([
            'fk_article_id' => 1,
            'price' => 3.49,
            'time' => '3-5 Werktage',
            'description' => 'Dieser Artikel braucht etwas länger beim Liefern'
        ]);
    }
}
