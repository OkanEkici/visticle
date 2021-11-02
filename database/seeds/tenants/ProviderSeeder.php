<?php

use Illuminate\Database\Seeder;
use App\Tenant\Provider;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for($i = 0; $i < 20; $i++){
            Provider::create([
                'name' => 'Anbieter '.$i,
                'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam',
                'fk_provider_type' => rand(1, 6)
            ]);
        }
    }
}
