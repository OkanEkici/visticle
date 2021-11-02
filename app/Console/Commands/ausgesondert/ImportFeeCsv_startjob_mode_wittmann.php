<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch, App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\WaWi;
use App\Tenant\Attribute_Set;
use Storage, Config;
use Log;
use App\Http\Controllers\Tenant\Providers\Fee\FeeController;
use Illuminate\Support\Facades\Artisan;

class ImportFeeCsv_startjob_mode_wittmann extends Command
{
    protected $signature = 'import:feecsv_startjob_mode_wittmann';
    protected $description = 'Importiert Artikel die von FEE per FTP abgelegt werden.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $customer = "mode-wittmann";    
        
        
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) 
        {   if($tenant->subdomain == $customer)
            {
                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                $feeController = new FeeController();
                //Log::channel('single')->info("Starte FeeJob: ".$tenant->subdomain);
                $feeController->importFeeCsv($tenant->subdomain); 
            }
           
        }
    }
}
