<?php

namespace App\Console\Commands\Check24;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use App\Tenant, App\Tenant\Branch;
use App\Tenant\Provider_Type;
use App\Helpers\Miscellaneous;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Attribute;
use App\Tenant\Category;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\WaWi;
use Storage, Config;
use Log;
use Illuminate\Support\Facades\Artisan;
use League\Csv\Writer;
use stdClass;
use App\Jobs\Manager\Controller\Check24\Check24ImportOrderJob;

class Check24ImportOrderAllTenants extends Command
{
    protected $signature = 'import:check24_import_order_all_tenants';
    protected $description = 'Importiert zu allen Check24-Kunden die Aufträge';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
       //Wir holen alle Check24-Kunden!
       $tenants=Tenant::all();

       foreach($tenants as $tenant){
           $subdomain=$tenant->subdomain;
           Miscellaneous::loadTenantDBConnection($tenant->subdomain);
           //Check24-Provider vorhanden?
           $provider_type=Provider_Type::query()->where('provider_key','check24')->first();

           if(!$provider_type){
               continue;
           }

           $provider=Provider::query()->where('fk_provider_type',$provider_type->id)->first();

           if(!$provider){
               continue;
           }

           //Befehl abfeuern
           Log::channel('cronjob')->info("Import Aufträge gestartet für Kunde \"{$subdomain}\"");
           $job=new Check24ImportOrderJob($subdomain);
           $job->handle();
           Log::channel('cronjob')->info("Import Aufträge beendet für Kunde \"{$subdomain}\"");
           //dispatch($job);
        }
    }
}
