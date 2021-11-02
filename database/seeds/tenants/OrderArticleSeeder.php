<?php

use Illuminate\Database\Seeder;
use App\Tenant\OrderArticle;

class OrderArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 1; $i < 99; $i++) {
            $orderId = $i;
            OrderArticle::create([
                'fk_article_id' => rand(1 , 99),
                'fk_article_variation_id' => null,
                'fk_order_id' => $orderId,
                'fk_orderarticle_status_id' => 1,
                'quantity' => rand(1, 5),
                'price' => rand(999, 4999),
            ]);
        }

    }
}
