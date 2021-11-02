<?php

namespace App\Console\Commands;

use App\Tenant;
use Illuminate\Console\Command;
use App\Http\Controllers\Tenant\Providers\Amazon\AmazonMWSController;

use App\Tenant\Provider;
use App\Tenant\Article;
use App\Tenant\Branch;
use App\Tenant\Synchro;
use Config;

class ExportAmznMWSInventoryFeed extends Command
{
    protected $signature = 'export:amazoninventoryfeed';

    protected $description = 'Exports article inventory data to Amazon as XML feed.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //Set DB Connection
        \DB::purge('tenant');
        $tenant = Tenant::where('subdomain','=',env('WSM_SUBDOMAIN','demo3'))->first(); //Rütz

        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);

        \DB::connection('tenant');

        //Amazon Controller
        $mwsController = new AmazonMWSController();

        //ARTICLE LOGIC START

        //All Amazon Provider
        $providers = Provider::whereHas('type', function($query) {
            return $query->where('provider_key', '=', 'amzn');
        })->get();
        
        foreach($providers as $provider) {
            $providerConfig = $provider->config()->first();
            if(!$providerConfig) { continue; }
            $configAttributes = $providerConfig->attributes()->get();
            $activeExport = $configAttributes->where('name','=','active_export')->first();
            if(!$activeExport || $activeExport->value != "on") 
            {continue;}

            // Check ob der letzte Artikelimport von fee durchgegangen ist
            if(Synchro::where('fk_synchro_type_id','=','1')->latest())
            { if(Synchro::where('fk_synchro_type_id','=','1')->latest()->first()->fk_synchro_status_id != "1"){continue;} }            

            $activeBranches = $configAttributes->where('name','=','branches_config')->first();
            if(!$activeBranches || empty($activeBranches)) 
            {continue;}
            $activeBranches = unserialize($activeBranches->value);

            $branchesMinQty = $configAttributes->where('name','=','branches_min_qty')->first();
            if(!$branchesMinQty) { $branchesMinQty = 0; }
            else { $branchesMinQty = $branchesMinQty->value; }
            
            $branchIds = [];
            foreach($activeBranches as $activeBranchId => $activeBranchVal) {
                $branchIds[] = $activeBranchId;
            }
            $articles = $provider->realArticles()->get();
            $items = [];
            foreach($articles as $article) 
            {
                $arVar = $article->variations()->first();
                $mainAr = $arVar->article()->first();							
                
                //Filter für Marken
                $brand = $mainAr->attributes()->where('name', '=', 'hersteller')->first();							
                if(!$brand) { continue; }     

                if($mainAr->active != '1') {continue;}    							
                if($arVar->active != '1') { continue; }    
                if($arVar->getEan() == '' || $arVar->getStandardPrice() == null) { continue; }    
                
                //if( $arVar->min_stock != null && $arVar->min_stock != '' && $arVar->min_stock > $branchesMinQty  )  { if($branchArticle->stock < $arVar->min_stock) { continue; } }
                
                $items[] = $mainAr;
                //echo "CountArticle: ".count($items)."\n";
            }                
            if(count($items)>0)
            {
                $articles = collect($items);
                $mwsController->sendInventoryFeed($articles, $branchIds, $branchesMinQty);
            }
            else
            {   //echo "\n".'Amazon MWS: Inventory Feed hat keine Items: SKIP';
                Log::info('Amazon MWS: Inventory Feed hat keine Items: SKIP');
            }
            
        }
        //ARTICLE LOGIC END
    }
}
