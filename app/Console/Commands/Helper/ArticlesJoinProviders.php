<?php

namespace App\Console\Commands\Helper;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use Config;

class ArticlesJoinProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'help:articles_join_providers {customer?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verknüpft alle Artikel mit allen Providern.';

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
        $domain=$this->argument('customer');
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();
        $tenants=null;
        if(!$tenant){
            $tenants=Tenant::all();
        }
        else{
            $tenants=Tenant::where('id',$tenant->id)->get();
        }

        foreach($tenants as $tenant){
            //Set DB Connection
            \DB::purge('tenant');
            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);
            \DB::connection('tenant');


            //Wir holen alle Provider!
            $providers=Provider::all();
            //alle Artikel holen
            $articles=Article::all();

            foreach($providers as $provider)
            {
                foreach($articles as $article)
                {
                    ArticleProvider::updateOrCreate(
                        ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                        ['active' => 1]
                    );
                }
            }
        }


    }
}
