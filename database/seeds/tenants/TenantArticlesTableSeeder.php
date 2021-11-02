<?php

use App\Tenant\Article;
use Illuminate\Database\Seeder;

class TenantArticlesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 0; $i < 100; $i ++) {
            Article::create([
                'name' => 'Artikel Nummer '.$i,
                'vstcl_identifier' => 'vstcl-xxxx-'.$i,
                'fk_wawi_id' => 1,
                'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam',
                'slug' => 'artikel-'.$i,
                'active' => true,
                'ean' => 'ean-xxxx-xxx'.$i,
            ]); 
        }
    }
}
