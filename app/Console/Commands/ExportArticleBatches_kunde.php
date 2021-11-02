<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use Illuminate\Console\Command;
use Config;
use Log;

class ExportArticleBatches_kunde extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:articlebatches_kunde {customer} {log}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports Article Batches to VS-Shop';

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
        $tenants = Tenant::all();

        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}

        $EchologAktiv = $this->argument('log');
        if($EchologAktiv=="false" || !$EchologAktiv){$EchologAktiv=false;}

        foreach($tenants as $tenant) {
                if($tenant->subdomain != $exec_for_customer){continue;}
                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                $shopController = new VSShopController();
                $shopController->article_batch($tenant->subdomain, $EchologAktiv);                
        }
    }
}
