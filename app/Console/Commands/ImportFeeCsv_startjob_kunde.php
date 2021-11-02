<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\WaWi;
use Storage, Config;
use Log;
use App\Console\Commands\Fee\ImportFeeCsv_startjob_dispatched;
use Illuminate\Support\Facades\Artisan;

class ImportFeeCsv_startjob_kunde extends Command
{
    protected $signature = 'import:feecsv_startjob_kunde {customer}';
    protected $description = 'Importiert Artikel für einen ausgesuchten Kunden, die von FEE per FTP abgelegt werden.';

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

        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        $job=new ImportFeeCsv_startjob_dispatched($customer);

        $job->handle();
        //dispatch($job);
    }
}
