<?php

namespace App\Console\Commands\Helper;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;
use App\Tenant\Provider;
use App\Tenant\Setting;
use App\Tenant\Category;
use Config;
use App\Helpers\Miscellaneous;
use App\Tenant\CategoryProvider;
use App\Tenant\Provider_Type;


class GiveCategoriesProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'help:give_categories_provider {customer} {provider_type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verknüpft alle Kategorien eines Kunden, die keinem Provider zugeordnet sind,
                              mit dem optional übergebenen Provider-Typen. Ist kein Provider-Typ gesetzt, so
                              wird standardmäßig "shop" genommen, also VSShop.';

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
            $this->info("Für die angegebene Domain \"{$domain}\" gibt es keinen Kunden!!!");
            return;
        }

        //Datenbank zum Kunden
        Miscellaneous::loadTenantDBConnection($domain);

        //Jetzt den Provider bestimmen
        $provider_type=null;
        $provider_type=$this->argument('provider_type');
        $provider=null;
        if(!$provider_type){
            //jetzt müssen wir den vorhandenen Provider Typen bestimmen, entweder ist es
            //der VSShop oder Shopware
            $provider_types=['shop','shopware'];
            foreach($provider_types as $provider_type){
                $provider_type_instanz=Provider_Type::query()->where('provider_key',$provider_type)->first();
                if(!$provider_type_instanz){
                    continue;
                }
                else{
                    $provider=Provider::query()->where('fk_provider_type',$provider_type_instanz->id)->first();
                    if($provider){
                        break;
                    }
                }
            }
        }
        else{
            $provider_type=Provider_Type::query()->where('provider_key',$provider_type)->first();
            if(!$provider_type){
                $this->info("Der gewünschte Provider-Typ ist nicht hinterlegt im System!");
                return;
            }
            else{
                $provider=Provider::query()->where('fk_provider_type',$provider_type->id)->first();
                if(!$provider){
                    $this->info("Zum ausgesuchten Provider-Typen ist kein Provider hinterlegt.");
                    return;
                }
            }
        }

        if(!$provider){
            return;
        }




        //Alle Kategorien holen, die keine Warengruppen sind und die keine Verknüpfung haben
        //in der Tabelle category_providers
        $categories=Category::query()->whereNull('fk_wawi_id')
                    ->leftJoin('category_providers','category_providers.fk_category_id','=','categories.id')
                    ->whereNull('category_providers.fk_category_id')
                    ->select('categories.id')
                    ->get();



        //Jetzt gehen wir die Kategorien durch und weisen sie unserem Provider zu!
        foreach($categories as $category){
            $keys=['fk_category_id'=>$category->id,'fk_provider_id'=>$provider->id];


            $category_provider=new CategoryProvider();
            $category_provider->fk_category_id=$category->id;
            $category_provider->fk_provider_id=$provider->id;
            $category_provider->save();

        }



    }
}
