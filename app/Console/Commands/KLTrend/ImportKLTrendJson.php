<?php

namespace App\Console\Commands\KLTrend;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\WaWi;
use Storage, Config;
use Log;
use App\Console\Commands\Fee\ImportFeeCsv_startjob_dispatched;
use App\Http\Controllers\Tenant\Providers\KLTrend\KLTrendController;
use Illuminate\Support\Facades\Artisan;

class ImportKLTrendJson extends Command
{
    protected $signature = 'import:kltrend_articles {customer} {--with_log}';
    protected $description = 'Importiert Artikel für einen ausgesuchten KLTrend-Kunden.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}
        if(!$exec_for_customer){return;}

        $customer=$exec_for_customer;
        //Tenant abgreifen nach dem Customer, also subdomain
        $tenant=Tenant::where('subdomain','=',$customer)->first();

        if(!$tenant){
            echo 'Der ausgesuchte Kunde \"' . $customer . '\" existiert nicht!';
            return 1;
        }


        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        //Jetzt überprüfen wir noch die Art des Kunden. Dies machen wir anhand des Warenwirtschaftsystems aus.
        $count=WaWi::query()->where('name','=','KLTrend')->count();

        if(!$count){
            echo 'Der ausgesuchte Kunde \"' . $customer . '\" benutzt nicht das Warenwirtschaftsystem \"KLTrend\"!';
            $wawi=Wawi::query()->first();
            if($wawi){
                echo '\n Folgendes Warenwirtschaftssystem kommt zum Einsatz: \"' . $wawi->name . '\"';
            }
            return 1;
        }
        //Jetzt kann unser eigentlicher Import beginnen
        $with_log=$this->option('with_log');
        $kltrend_controller=new KLTrendController();
        $kltrend_controller->importKLTrendJson($customer,$with_log);
    }
}
