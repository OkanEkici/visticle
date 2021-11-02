<?php

namespace App\Console\Commands\Manager\Plattform\VSShop;

use App\AccountType;
use App\Helpers\Miscellaneous;
use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Tenant\Article;
use App\Tenant\Provider;
use App\Tenant\ArticleProviderSync;
use Illuminate\Console\Command;
use Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SynchroResetterOfAllTenants extends Command
{
    protected $signature = 'vsshop:reset_syncros_of_all_tenants';

    protected $description = 'Dieser Befehl löscht die Syncro-Tabellen aller Kunden und überträgt alle Daten an den Shop!';

    public function __construct(){parent::__construct();}

    public function handle()
    {

        $except=[
            'demo1',
            'demo2',
            'demo3',
            'fashionundtrends',
            'modemai',
            'melchior',
            'vanhauth',
            'wunderschoen-mode',
            'mode-wittmann',
            'senft',
            'stilfaktor',
            'wildhardt',
            'dhtextil',
            'mukila',
            'ruetz',
            'heson',
            'fischer-stegmaier',
            'fruehauf',
            'obermann',
            'ts-cutting',
            'mayer-burghausen',
            'pascha',


            'zoergiebel',
        ];


        Log::channel('vsshop')->info('Starte SYNCRO-RESET für Alle');
        $tenants=Tenant::all();

        foreach($tenants as $tenant){
            if(in_array($tenant->subdomain,$except)){
                continue;
            }

            try{
                Miscellaneous::loadMainDBConnection();
                Log::channel('vsshop')->info("Starte Syncro-Reset für \"{$tenant->getSubdomain()}\"");
                Artisan::call('vsshop:reset_syncros',['customer'=>$tenant->subdomain]);
                Log::channel('vsshop')->info("Beende Syncro-Reset für \"{$tenant->getSubdomain()}\"");
            }
            catch(\Exception $e){
                Log::channel('vsshop')->error($e->getMessage() . '---' . $e->getFile() . '---' . $e->getLine());
            }
        }

        //Und am Ende Übertragen wir alles an den Shop!!!!

        $tenants=Tenant::all();
        foreach($tenants as $tenant){
            if(in_array($tenant->subdomain,$except)){
                continue;
            }
            try{
                Miscellaneous::loadMainDBConnection();
                Log::channel('vsshop')->info("Starte Article-Fill für \"{$tenant->getSubdomain()}\"");
                Artisan::call('export:article_fill',['customer'=>$tenant->subdomain,'log'=>1]);
                Log::channel('vsshop')->info("Beende Article-Fill für \"{$tenant->getSubdomain()}\"");
            }
            catch(\Exception $e){
                Log::channel('vsshop')->error($e->getMessage() . '---' . $e->getFile() . '---' . $e->getLine());
            }
        }

        //Danach schreiben wir etwas eindeutiges in die Log!
        Log::channel('vsshop')->info('Beende SYNCRO-RESET für Alle');

    }
}
