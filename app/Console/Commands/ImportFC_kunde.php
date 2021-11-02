<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article;
use App\Tenant\Setting;
use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;
use Config;

use App\Console\Commands\FashionCloud\ImportFC_fashionundtrends;
use App\Console\Commands\FashionCloud\ImportFC_mayer_burghausen;
use App\Console\Commands\FashionCloud\ImportFC_melchior;
use App\Console\Commands\FashionCloud\ImportFC_modemai;
use App\Console\Commands\FashionCloud\ImportFC_wildhardt;
use App\Console\Commands\FashionCloud\ImportFC_wunderschoen_mode;
use App\Console\Commands\FashionCloud\ImportFC_zoergiebel;
use App\Console\Commands\FashionCloud\ImportFC_obermann;
use App\Console\Commands\FashionCloud\ImportFC_schwoeppe;
use App\Console\Commands\FashionCloud\ImportFC_vanhauth;
use App\Console\Commands\FashionCloud\ImportFC_frauenzimmer;
use App\Console\Commands\FashionCloud\ImportFC_keller;
use App\Console\Commands\FashionCloud\ImportFC_neheim;
use App\Console\Commands\FashionCloud\ImportFC_senft;
use App\Console\Commands\FashionCloud\ImportFC_bernard;

class ImportFC_kunde extends Command
{
    protected $signature = 'import:fashioncloud_kunde {customer} {--all}';
    protected $description = 'Importiert den Content von Fashioncloud für neue Artikel.';

    public function __construct(){parent::__construct();}

    public function handle()
    {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}
        if(!$exec_for_customer){return;}
        switch($exec_for_customer)
        {   case"fashionundtrends":(new ImportFC_fashionundtrends())->handle();break;
            case"melchior":(new ImportFC_melchior())->handle();break;
            case"modemai":(new ImportFC_modemai())->handle();break;
            case"wildhardt":(new ImportFC_wildhardt())->handle();break;
            case"wunderschoen_mode":(new ImportFC_wunderschoen_mode())->handle();break;
            case"zoergiebel":(new ImportFC_zoergiebel($this->option('all')))->handle();break;
            case"mayer_burghausen":(new ImportFC_mayer_burghausen())->handle();break;
            case"obermann":(new ImportFC_obermann())->handle();break;
            case"schwoeppe":(new ImportFC_schwoeppe())->handle();break;
            case"vanhauth":(new ImportFC_vanhauth())->handle();break;
            case"frauenzimmer":(new ImportFC_frauenzimmer())->handle();break;
            case "neheim" : (new ImportFC_neheim)->handle();break;
            case "keller" : (new ImportFC_keller())->handle();break;
            case "senft" : (new ImportFC_senft())->handle();break;
            case "bernard" : (new ImportFC_bernard())->handle();break;
        }
    }
}
