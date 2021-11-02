<?php

namespace App\Console\Commands\Manager\Plattform;

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
use App\Tenant\Provider_Config;
use Illuminate\Support\Facades\Artisan;


class CreatePlattform extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plattform:create_plattform {customer} {provider_key} {provider_type_name} {provider_type_description} {provider_name} {provider_description} {url?} {apikey?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dieser Befehl legt für einen Kunden eine Plattform an.
                              Darüberhinaus verknüpft sie auch alle bestehenden Artikel im
                              Anschluss mit der neu eingerichteten Plattform.';

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
        $provider_key=$this->argument('provider_key');
        $provider_type=null;
        $provider=null;

        $provider_type_name=$this->argument('provider_type_name');
        $provider_type_description=$this->argument('provider_type_description');


        $provider_type=Provider_Type::query()->where('provider_key',$provider_key)->first();
        if(!$provider_type){
            //Wir legen den Provider-Typen an
            $data=[
                'provider_key'=>$provider_key,
                'name'=>$provider_type_name,
                'description'=>$provider_type_description,
            ];
            $provider_type=Provider_Type::create($data);
        }

        //Provider daten holen
        $provider_name=$this->argument('provider_name');
        $provider_description=$this->argument('provider_description');

        //schauen wir, ob wir den Provider bereits haben, wenn ja verlassen wir die Funktion
        $provider=Provider::query()->where('fk_provider_type',$provider_type->id)
                                    ->where('name',$provider_name)
                                    ->first();
        if($provider){
            $this->info("Der gewünschte Provider ist bereits hinterlegt.");
            return;
        }
        
        //Wir legen den Provider neu an
        //optionale url und apikey holen
        $url=$this->argument('url');
        $apikey=$this->argument('apikey');
        $data=[
            'name'=>$provider_name,
            'description'=>$provider_description,
            'fk_provider_type' => $provider_type->id,
            'url'=>$url,
            'apikey'=>$apikey,
        ];
        $provider=Provider::create($data);

        //Provider Config noch anlegen
        $data=[
            'fk_provider_id'=>$provider->id,
        ];
        $provider_config=Provider_Config::create($data);

        //Nun verknüpfen wir alle Artikel mit dem neu erstellten Provider
        $data=[
            'customer'=>$domain,
            'provider_id'=>$provider->id,
        ];
        Artisan::call('plattform:articles_join_providers',$data);



    }
}
