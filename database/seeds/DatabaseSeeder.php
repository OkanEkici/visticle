<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class); //Base
        $this->call(TenantTableSeeder::class); //Base
        $this->call(TenantUsersTableSeeder::class);
        $this->call(WaWiSeeder::class); //Base
        //$this->call(TenantArticlesTableSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(ConfigPaymentSeeder::class);
        $this->call(ConfigPaymentAttributeSeeder::class);
        $this->call(ConfigShipmentSeeder::class);
        $this->call(ConfigShipmentAttributeSeeder::class);
        $this->call(ProviderTypeSeeder::class); //Base
        $this->call(ProviderSeeder::class);
        //$this->call(ArticleProviderSeeder::class);
        $this->call(OrderStatusSeeder::class); //Base
        //$this->call(OrderSeeder::class);
        //$this->call(OrderAttributeSeeder::class);
        $this->call(OrderArticleStatusSeeder::class); //Base
        //$this->call(OrderArticleSeeder::class);
        $this->call(OrderDocumentTypeSeeder::class); //Base
        $this->call(InvoiceStatusSeeder::class); //Base
        //$this->call(InvoiceSeeder::class);
        $this->call(SettingsTypeSeeder::class); //Base
        $this->call(SettingSeeder::class); //Base
        $this->call(SettingsAttributeSeeder::class); //Base
        $this->call(CommissionOrderStatusSeeder::class); //Base
        //$this->call(ArticleShipmentSeeder::class);
        //$this->call(ArticlePriceSeeder::class);
    }
}
