<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use Illuminate\Console\Command;
use Config;
use Log;

class ExportValidateData extends Command
{
    protected $signature = 'export:shopvalidatedata';

    protected $description = 'Exports Shop Validate IDs Data to VS-Shop';

    public function __construct()
    { parent::__construct();}

    public function handle()
    {
        $tenants = Tenant::all();

        foreach($tenants as $tenant)
        {
            $DataCustomers = [
                'melchior',
                'fashionundtrends',
                'wildhardt',
                'modemai',
                'wunderschoen-mode',
                'mukila',
                'zoergiebel'
            ];
            $ExcludeShopDataCustomers = ['tscutting','demo1','demo2','demo3','stilfaktor'
            ,'vanhauth','dhtextil','mode-wittmann','senft','fischer-stegmaier'
            ,'fruehauf','obermann','pascha','mayer-burghausen','cosydh','keller', 'haider','romeiks'];
            if(in_array($tenant->subdomain, $ExcludeShopDataCustomers)) { continue; }
            if(!in_array($tenant->subdomain, $DataCustomers)) { continue; }

                //Set DB Connection
                \DB::purge('tenant');
                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);
                \DB::connection('tenant');

                $shopController = new VSShopController();
                $shopController->validate_shop_items($tenant->subdomain,true);
        }
    }
}
