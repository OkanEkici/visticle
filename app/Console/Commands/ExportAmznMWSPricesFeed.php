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

class ExportAmznMWSPricesFeed extends Command
{
    protected $signature = 'export:amazonpricesfeed';

    protected $description = 'Exports article price data to Amazon MWS as XML feed.';

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
                    if(!$providerConfig) {
                        continue;
                    }
                    $configAttributes = $providerConfig->attributes()->get();
                    $activeExport = $configAttributes->where('name','=','active_export')->first();
                    if(!$activeExport || $activeExport->value != "on") {
                        continue;
                    }
                    // Check ob der letzte Artikelimport von fee durchgegangen ist
                    if(Synchro::where('fk_synchro_type_id','=','1')->latest())
                    { if(Synchro::where('fk_synchro_type_id','=','1')->latest()->first()->fk_synchro_status_id != "1"){continue;} }
        
                    //$articles = $provider->realArticles()->get();
                    //TODO: Submit all Articles
                    $articles = Article::all();
                    //Send feed to amazon
                     $mwsController->sendPricesFeed($articles);
                }
                //ARTICLE LOGIC END
    }
}
