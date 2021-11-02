<?php

use Illuminate\Database\Seeder;
use App\Tenant\ArticleProvider;

class ArticleProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 1; $i < 30; $i++) {
            ArticleProvider::create([
                'fk_provider_id' => 1,
                'fk_article_id' => $i,
                'fk_article_variation_id' => null,
                'active' => true,
            ]);
        }
    }
}
