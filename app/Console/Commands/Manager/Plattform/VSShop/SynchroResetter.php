<?php

namespace App\Console\Commands\Manager\Plattform\VSShop;

use App\AccountType;
use App\Helpers\Miscellaneous;
use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Tenant\Article;
use App\Tenant\Provider;
use App\Tenant\ArticleProviderSync;
use Illuminate\Console\Command;
use Config;
use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SynchroResetter extends Command
{
    protected $signature = 'vsshop:reset_syncros {customer}';

    protected $description = 'Dieser Befehl löscht die Syncro-Tabellen eines Kunden';

    public function __construct(){parent::__construct();}

    public function handle()
    {

        $domain=$this->argument('customer');
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();

        if(!$tenant){
            $this->info("Für die angegebene Domain \"{$domain}\" gibt es keinen Kunden!!!");
            return;
        }

        //Datenbank zum Kunden
        Miscellaneous::loadTenantDBConnection($domain);

        //provider holen!
        $provider=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','shop');
        })->first();

        if(!$provider){
            $this->info("Es ist kein Provider für den VSShop im System hinterlegt!");
            return;
        }

        //Alles resetten
        ArticleProviderSync::query()->delete();

    }
}
