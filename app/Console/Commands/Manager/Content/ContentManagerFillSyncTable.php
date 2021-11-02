<?php

namespace App\Console\Commands\Manager\Content;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Tenant\ArticleProviderSync;
use Config;
use App\Helpers\Miscellaneous;
use App\Tenant\Provider_Type;
use App\Manager\Content\ContentManager;

class ContentManagerFillSyncTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content-manager:fill_sync_table {customer} {provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erstellt für alle Artikel aus der Article_Branch-Tabelle von und für einen bestimmten
                              Provider und Kunden einen Eintrag in der Sync-Tabelle "Article_provider_syncs". Dabei werden die Datensätze
                              abgelegt, als wären die Artikel eine Neuanlage.';

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
        $provider_type=Provider_Type::query()->where('provider_key','=','amzn')->first();
        $provider=Provider::query()->where('fk_provider_type','=',$provider_type->id)->first();

        if(!$provider){
            $this->info("Es ist kein Provider hinterlegt mit dem Provider-Typ \"{$provider_text}\"");
            return;
        }


        //Jetzt holen wir alle Article-IDs aus der ArticleProvider für den ausgesuchten Provider
        $article_ids=ArticleProvider::query()->where('fk_provider_id','=',$provider->id)
                        ->where('active','1')
                        ->select('fk_article_id')
                        ->get()
                        ->pluck('fk_article_id')
                        ->toArray();


        //Die Artikel dem Content-Manager übergeben, als wären es Neuanlagen
        $articles=Article::query()->whereIn('id',$article_ids)->get();

        Miscellaneous::loadTenantDBConnection($domain);
        $content_manager=new ContentManager();

        //Die Nummern holen für Operation und Priorität
        $operation_number=config("content-manager.operations.insert");
        $priority_number=config("content-manager.priorities.scheduled");
        foreach($articles as $article)
        {
            $data=[
                'fk_article_id'=>$article->id,
                'fk_provider_id'=>$provider->id,
                'operation'=>$operation_number,
                'subject'=>null,
                'subject_id'=>null,
                'context'=>null,
                'context_value'=>null,
                'priority'=>$priority_number,
            ];
            $article_provider_sync=ArticleProviderSync::updateOrCreate($data);
        }
    }
}
