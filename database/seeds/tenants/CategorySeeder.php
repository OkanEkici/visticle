<?php

use Illuminate\Database\Seeder;
use App\Tenant\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Category::create([
            'name' => 'Hosen',
            'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea',
            'fk_parent_category_id' => null,
            'slug' => 'hosen',
        ]);

        Category::create([
            'name' => 'Blusen',
            'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea',
            'fk_parent_category_id' => null,
            'slug' => 'blusen',
        ]);

        Category::create([
            'name' => 'Jeans',
            'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea',
            'fk_parent_category_id' => 1,
            'slug' => 'jeans',
        ]);
    }
}
