<?php

namespace App\Console\Commands\Manager\Plattform\VSShop;

use App\AccountType;
use App\Helpers\Miscellaneous;
use App\Tenant;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Tenant\Article;
use Illuminate\Console\Command;
use Config;
use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SendSynchrosToAllTenants extends Command
{
    protected $signature = 'vsshop:send_syncros_to_all_tenants {priority}';

    protected $description = 'Stösst den Syncro-Export für alle Kunden an. Dabei wird die Priorität mit angegeben \"immediate\" oder \"scheduled\"';

    public function __construct(){parent::__construct();}

    public function handle()
    {

        //Alle verfügbaren Prioritäten holen
        $priorities=config('content-manager.priorities');

        $priority=$this->argument('priority');
        if(!$priorities[$priority]){
            $this->info("Die angegebene Priorität \"{$priority}\" ist nicht registriert!");
            return;
        }

        $tenants = Tenant::all();


        foreach($tenants as $tenant) {
                Miscellaneous::loadMainDBConnection();
                Artisan::call('vsshop:send_syncros',['customer'=>$tenant->subdomain,'priority'=>$priority]);
        }

    }
}
