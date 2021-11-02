<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch, App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\WaWi;
use App\Tenant\Attribute_Set;
use Storage, Config;
use Log;

use Illuminate\Support\Facades\Artisan;

class ImportFeeCsv extends Command
{
    protected $signature = 'import:feecsv';
    protected $description = 'Importiert Artikel die von FEE per FTP abgelegt werden.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}

        $customers = Storage::disk('customers')->directories();
        $tenants = Tenant::inRandomOrder()->get();
        $tenantTeams = [];
        $deltaCustomers = [
            'melchior',
            'stilfaktor',
            'vanhauth',
            'demo2',
            'demo3',
            'dhtextil',
            'fashionundtrends',
            'mode-wittmann',
            'wildhardt',
            'modemai',
            'wunderschoen-mode',
            'senft',
            'fischer-stegmaier',
            'mukila',
            'fruehauf',
            'obermann',
            'pascha',
            'mayer-burghausen'
            ,'cosydh','keller','pk-fashion'
        ];
        $excludeCustomers = ['demo3'];

        if($exec_for_customer && $exec_for_customer != "")
        { $deltaCustomers = [$exec_for_customer]; }
        foreach ($tenants as $tenant) { $tenantTeams[] = $tenant->subdomain; }


        foreach($tenants as $tenant) {

            if(in_array($tenant->subdomain, $excludeCustomers)) { continue; }
            if(!in_array($tenant->subdomain, $tenantTeams)) { continue;  } // Tenant nicht eingerichtet
            if(!in_array($tenant->subdomain, $customers)) { continue;  } // Ordner nicht vorhanden
            if(!in_array($tenant->subdomain, $deltaCustomers)) { continue; }

            $exitCode = Artisan::call('import:feecsv_startjob', [
                'customer' => $tenant->subdomain
            ]);
        }

    }


}

