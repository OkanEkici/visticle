<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant\Article;
use Config;
use Log;

class FillShopwareShop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fill:shopware';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Füllt den Shopwareshop';

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
        $subdomain = 'stilfaktor';
        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) {
            return;
        }
        Log::channel('single')->info("Starte fill:shopware");

        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        $providerController = new ShopwareAPIController();

        $articles = Article::whereHas('images')->whereDoesntHave('attributes', function($query) {
            $query->where('name','=','sw_id')->whereNotNull('value');
        })->get();

        foreach($articles as $article) {
            $providerController->create_article($article);
            sleep(2);
            $providerController->update_article($article);
            sleep(5);
            $providerController->update_article($article);
        }
    }
}
