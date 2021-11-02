<?php

namespace App\Console\Commands\Manager\Plattform;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Tenant\Category;
use Config;
use App\Helpers\Miscellaneous;
use App\Tenant\CategoryProvider;
use App\Tenant\Provider_Type;
//use App\Console\Commands\Helper\GiveCategoriesProvider;
use Illuminate\Support\Facades\Artisan;


class GiveCategoriesProviderAllTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plattform:give_categories_provider_all_tenants  {provider_type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verknüpft alle Kategorien eines Kunden, die keinem Provider zugeordnet sind,
                              mit dem optional übergebenen Provider-Typen. Ist kein Provider-Typ gesetzt, so
                              wird standardmäßig "shop" genommen, also VSShop.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $all_tenants=Tenant::all();
        foreach($all_tenants as $tenant){
            Miscellaneous::loadTenantDBConnection($tenant->subdomain);
            $data=[];
            $data['customer']=$tenant->subdomain;

            if($this->argument('provider_type')){
                $data['provider_type']=$this->argument('provider_type');
            }
            Artisan::call('help:give_categories_provider',$data);

        }
    }
}
