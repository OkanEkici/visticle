<?php

use Illuminate\Database\Seeder;
use App\Tenant\Article_Image;

class ArticleImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Article_Image::create([
            'fk_article_id' => 1,
            'location' => 'local'
        ]);
    }
}
