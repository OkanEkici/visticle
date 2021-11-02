<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\Tenant\Wawis\Advarics\AdvaricsApiController;
use App\Tenant;
use Log;

class ImportAdvaricsArticles extends Command
{
    protected $signature = 'import:advaricsarticles';
    protected $description = 'Importiert die Artikel von Advarics ins Visticle';

    public function __construct()
    { parent::__construct();}

    public function handle()
    {
        $advaricsCustomers = [
            'zoergiebel'
        ];

        foreach($advaricsCustomers as $advaricsCustomer) {
            $tenant = Tenant::where('subdomain', '=', $advaricsCustomer)->first();
            if(!$tenant) { Log::channel('single')->info('Advarics Customer not found as Visticle Tenant: '.$advaricsCustomer); continue; }
            //Service Id Check
            if(!$tenant->advarics_service_id || $tenant->advarics_service_id == '') { Log::channel('single')->info('Advarics Customer Key fehlt: '.$advaricsCustomer); continue;  }

            $apiController = new AdvaricsApiController($tenant->advarics_service_id);
            $apiController->getArticles($advaricsCustomer,true);

        }
    }
}
