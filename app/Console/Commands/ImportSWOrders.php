<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant;
use Config;
use Log;

class ImportSWOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:sworders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert Bestellungen aus Shopwareshops der Kunden';

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
        Log::channel('single')->info("Starte import:sworders");
        foreach($tenants as $tenant) {
            //Set DB Connection
            \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);
            \DB::connection('tenant');
            $swController = new ShopwareAPIController();
            $swShops = $swController->getShops();
            if(empty($swShops)) {
                continue;
            }
            $swController->getOrders();
        }
    }
}
