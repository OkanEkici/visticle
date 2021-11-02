<?php

use Illuminate\Database\Seeder;
use App\Tenant\Provider_Type;

class ProviderTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Provider_Type::create([
            'provider_key' => 'amzn',
            'description' => 'Amazon als Anbieter',
            'name' => 'Amazon Seller Platform'
        ]);

        Provider_Type::create([
            'provider_key' => 'shop',
            'description' => 'Eigene Onlineshops',
            'name' => 'Onlineshop'
        ]);

        Provider_Type::create([
            'provider_key' => 'facebook',
            'description' => 'Facebook Shop API',
            'name' => 'Facebook Shopping'
        ]);

        Provider_Type::create([
            'provider_key' => 'zalando',
            'description' => 'Zalando Partner API',
            'name' => 'Zalando'
        ]);

        Provider_Type::create([
            'provider_key' => 'zalando',
            'description' => 'Google Shopping API',
            'name' => 'Google Shopping'
        ]);

        Provider_Type::create([
            'provider_key' => 'fee',
            'description' => 'FEE Warenwirtschaft API',
            'name' => 'FEE WaWi'
        ]);

        Provider_Type::create([
            'provider_key' => 'zalando_cr',
            'description' => 'Zalando Connected Retail',
            'name' => 'Zalando Connected Retail'
        ]);

        Provider_Type::create([
            'provider_key' => 'shopware',
            'description' => 'Shopware Shop',
            'name' => 'Shopware Shop'
        ]);
    }
}
