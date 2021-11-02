<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use Illuminate\Console\Command;
use Config;

class ExportStockBatches extends Command
{
    protected $signature = 'export:stockbatches';

    protected $description = 'Export Stock batches to VS-Shops';

    public function __construct(){parent::__construct();}

    public function handle()
    {
        $tenants = Tenant::all();

        foreach($tenants as $tenant) {

                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                $shopController = new VSShopController();
                $shopController->stock_batch();
        }
    }
}
