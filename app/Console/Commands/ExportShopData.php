<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Tenant\Article; use App\Tenant\Branch;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use Illuminate\Console\Command;
use Config;
use Log;

class ExportShopData extends Command
{
    protected $signature = 'export:shopdata';

    protected $description = 'Exports Shop Data to VS-Shop';

    public function __construct(){ parent::__construct();}

    public function handle()
    {
        $tenants = Tenant::all();

        foreach($tenants as $tenant) {
            $ShopDataCustomers = ['ts-cutting'];
            if(!in_array($tenant->subdomain, $ShopDataCustomers)) { continue; }            
            
            //Set DB Connection
            \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);
            \DB::connection('tenant');

            // TS Cutting Bestand 1000 beibehalten
            $articles = Article::all(); $branches = Branch::get();
            foreach($articles as $article) {$variations = $article->variations()->get();
                foreach($variations as $variation) {
                    foreach($branches as $branch){ $variation->updateOrCreateStockInBranch($branch, 1000, 111); }
                }
            }
            
            $shopController = new VSShopController();
            $shopController->shop_data($tenant->subdomain);       
            Log::info("ts-cutting - export:shopdata > abgeschlossen");         
        }
    }
}
