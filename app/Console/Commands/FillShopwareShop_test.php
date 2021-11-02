<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Tenant;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use App\Tenant\Article;
use Config;
use Log;
use stdClass;
use App\Tenant\Category;

class FillShopwareShop_test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fill:shopware_test';

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
        $echolog = 1;
        $subdomain = 'stilfaktor';
        $tenant = Tenant::where('subdomain','=',$subdomain)->first();
        if(!$tenant) {
            return;
        }
        if($echolog == 0){Log::channel('single')->info("Starte fill:shopware");}
        else{echo "Starte fill:shopware-Test\n";}


        //Set DB Connection
        \DB::purge('tenant');
        $config = Config::get('database.connections.tenant');
        $config['database'] = $tenant->db;
        $config['username'] = $tenant->db_user;
        $config['password'] = decrypt($tenant->db_pw);
        config()->set('database.connections.tenant', $config);
        \DB::connection('tenant');

        $providerController = new ShopwareAPIController();

        //$article = Article::query()->where('id', '=', '3427')->first();
/*
        $articles = Article::whereHas('images')->whereDoesntHave('attributes', function($query) {
            $query->where('name','=','sw_id')->whereNotNull('value');
        })->get();


        foreach($articles as $article) {
            if($echolog == 1){ echo "\n".$article->id." sende..."; }
            $providerController->create_article($article,null,$echolog);

        }


        $articles = Article::whereDoesntHave('images')->whereDoesntHave('attributes', function($query) {
            $query->where('name','=','sw_id')->whereNotNull('value');
        })->get();

        dd($articles);

        foreach($articles as $article) {
            if($echolog == 1){ echo "\n".$article->id." sende..."; }
            $providerController->create_article($article,null,$echolog);
        }
*/

    $article = Article::query()->where('id', '=', '3134')->first();


    if($echolog == 1){ echo "\n".$article->id." sende..."; }
    $res = $providerController->create_article($article,null,$echolog);
dd($res);

    }
}
