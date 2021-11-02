<?php

namespace App\Console\Commands;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Tenant\Article;
use Illuminate\Console\Command;
use Config;
use Log;
use Illuminate\Http\Request;
use App\Jobs\Manager\Controller\DoArticleBatchPerTenant;

class ExportArticleBatches extends Command
{
    protected $signature = 'export:articlebatches';

    protected $description = 'Exports Article Batches to VS-Shop';

    public function __construct(){parent::__construct();}

    public function handle()
    {


        $tenants = Tenant::inRandomOrder()->get();
        if($tenants)
        {
            foreach($tenants as $tenant) {
                $excludeCustomers = ['demo1','demo2', 'demo3'];
                if(in_array($tenant->subdomain, $excludeCustomers)) { continue; }



                    //Set DB Connection
                    /*
                    \DB::purge('tenant');
                    $config = Config::get('database.connections.tenant');
                    $config['database'] = $tenant->db;
                    $config['username'] = $tenant->db_user;
                    $config['password'] = decrypt($tenant->db_pw);
                    config()->set('database.connections.tenant', $config);
                    \DB::connection('tenant');
                    */

                    //####
                    //$article=Article::query()->where('id',1)->first();
                    //dd($article->toArray());
                    //####

                    /*
                    $request= new Request();
                    $shopController = new VSShopController();
                    //Log::channel('single')->info("prepare Batch: ".$tenant->subdomain);
                    $shopController->article_batch($tenant->subdomain);
                    */

                    //$shopController->syncShopArticles($request,$tenant->subdomain);


                    //Job für jeden einzelnen Kunden erstellen und abfeuern
                    echo 'abfeuern';
                    $tenant_job=new DoArticleBatchPerTenant($tenant->subdomain);
                   $tenant_job->handle();
                    echo 'abgefeuert';
            }
        }

    }
}
