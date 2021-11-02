<?php

use Illuminate\Database\Seeder;
use App\Tenant\Article_Price;

class ArticlePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 1; $i < 30; $i++) {
            Article_Price::create([
                'fk_article_id' => $i,
                'name' => 'standard',
                'value' => '19.99'
            ]);

            Article_Price::create([
                'fk_article_id' => $i,
                'name' => 'discount',
                'value' => '16.99'
            ]);
        }
        
    }
}
