<?php

namespace App\Console\Commands\Fee;

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
use Exception;
use Illuminate\Support\Facades\Artisan;


class ImportFeeCsv_startjob_dispatched
{
    private $customer;

    public function __construct($customer){ $this->customer=$customer; }

    public function handle(){
        if($this->customer==null){ return; }
        $customer=$this->customer;
        //Tenant/Customer > subdomain
        $tenant=Tenant::where('subdomain','=',$customer)->first();

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

        echo 'import gestartet für ' . $customer;
        $feeController->importFeeCsv($tenant->subdomain);
        echo 'import beendet für ' . $customer;
    }
}
