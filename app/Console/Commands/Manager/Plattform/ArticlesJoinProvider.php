<?php

namespace App\Console\Commands\Manager\Plattform;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Helpers\Miscellaneous;
use Config;

class ArticlesJoinProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plattform:articles_join_providers {customer} {provider_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verknüpft alle Artikel eines Kunden mit einem Provider.';

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
        
        if(!$tenant){
            $this->info("Für die angegebene Domain \"{$domain}\" gibt es keinen Kunden!!!");
            return;
        }

        //Datenbank zum Kunden
        Miscellaneous::loadTenantDBConnection($domain);

        //provider holen!
        $provider_id=$this->argument('provider_id');
        $provider=Provider::query()->where('id',$provider_id)->first();
        if(!$provider){
            $this->info("Es ist kein Provider mit der ID \"{$provider_id}\" im System hinterlegt!");
            return;
        }

        //Nun holen wir alle Artikel und verknüpfen diese mit dem ausgesuchten Provider
        $articles=Article::all();
        foreach($articles as $article)
        {
            ArticleProvider::updateOrCreate(
                ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                ['active' => 1]
            );
        }


    }
}
