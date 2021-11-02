<?php

namespace App\Console\Commands\Manager\Content;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use Config;
use App\Helpers\Miscellaneous;
use App\Tenant\Provider_Type;

class ContentManagerArticlesJoinProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content-manager:articles_join_provider {customer} {provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verknüpft alle Artikel mit einem ausgesuchten Provider(Provider-Typ) für einen ausgesuchten Kunden.';

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

        if(!$domain){
            $this->info("Kunde angeben bitte!");
            return;
        }
        $tenant = Tenant::query()->where('subdomain','=',$domain)->first();

        if(!$tenant){
            $this->info("Es ist kein Kunde für die Subdomain \"{$domain}\" hinterlegt!");
            return;
        }
        Miscellaneous::loadTenantDBConnection($domain);

        $provider_text=$this->argument('provider');

        if(!$provider_text){
            $this->info("Provider angeben bitte!");
            return;
        }
        $provider_type=Provider_Type::query()->where('provider_key','=',$provider_text)->first();
        $provider=Provider::query()->where('fk_provider_type','=',$provider_type->id)->first();

        if(!$provider){
            $this->info("Es ist kein Provider hinterlegt mit dem Provider-Typ \"{$provider_text}\"");
            return;
        }





        //alle Artikel holen
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
