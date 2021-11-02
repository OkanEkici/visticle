<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant\Article;
use Config;
use Log;

class ExportShopwareArticleBatches extends Command
{
    protected $signature = 'export:shopware_batch';
    protected $description = 'Batcht alle Artikel im Shopwareshop';
    public function __construct()
    { parent::__construct();}

    public function handle()
    {
        $subdomain = 'stilfaktor';
        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) { return; }
        Log::channel('single')->info("Starte Shopware-Batch: ".$subdomain);

        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        $providerController = new ShopwareAPIController();
        $providerController->article_batch($subdomain);
        
    }
}
