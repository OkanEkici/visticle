<?php

namespace App\Console\Commands;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Setting;
use App\Tenant\Branch; use App\Tenant\Category;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use Storage, Config;
use Log;
use App\Tenant\ArticleProvider; use App\Tenant\Provider;
use App\Tenant\Settings_Type;
use App\Tenant\Settings_Attribute;

class ExportZalandoCSV extends Command
{
    protected $signature = 'export:zalandocsv {customer}';

    protected $description = 'Creates and exports a CSV to Zalando Connected Retail';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}

        $customers = Storage::disk('customers')->directories();

        $tenants = Tenant::all();
        $tenantTeams = [];
        $client = new Client();
        $url = env('ZALANDO_CR_API_URL', 'https://merchants-connector-importer.zalandoapis.com');

        foreach ($tenants as $tenant) { $tenantTeams[] = $tenant->subdomain; }

        $aktiveSALETenants = ['fischer-stegmaier']; // Für Hersteller Rabatte


        foreach($customers as $customer) {
            if(!in_array($customer, $tenantTeams)) { continue;  }
            if($exec_for_customer && $exec_for_customer != $customer){continue;}

            $customerFolders = Storage::disk('customers')->directories($customer);
            if(in_array($customer.'/csv_zalando', $customerFolders)) {
                //Set DB Connection
                \DB::purge('tenant');
                $tenant = $tenants->where('subdomain','=', $customer)->first();

                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);

                \DB::connection('tenant');

                if(!Setting::isActiveZalandoCRParnter()){ continue; }

                $credentials = Setting::getZalandoCRCredentials();
                if($credentials['client_id'] == null || $credentials['api_key'] == null)
                { continue; }

                $date = date('YmdHis');
                $settingType = Settings_Type::where('name','=', 'partner')->first();
                $setting = Setting::where('fk_settings_type_id','=',$settingType->id)->first();

                $synchroType = Synchro_Type::where('key','=','zalando_csv_export')->first();
                $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
                $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
                $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
                $hasSynchro = true;
                $synchro = null;
                if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS) {
                    $hasSynchro = false;
                }
                if($hasSynchro) {
                    $synchro = Synchro::create(
                        [
                            'fk_synchro_type_id' => $synchroType->id,
                            'fk_synchro_status_id' => $inProgressSynchroS->id,
                            'start_date' => date('Y-m-d H:i:s')
                        ]
                    );
                }
                $success_count = 0;
                $error_count = 0;
                $expected_count = 0;
                $articleCount = 0;

                $filePath = storage_path()."/customers/".$customer."/csv_zalando/";
                $branches = Setting::getZalandoCRBranches();
                $brands = Setting::getZalandoCRBrands();
                $minPrice = Setting::getZalandoMinPrice();
                $items = [];
                $ZalandoProvider = Provider::where('fk_provider_type', '=', '2')->first();

                foreach($branches as $branchId => $branchVal)
                {
                    if(!$ZalandoProvider){continue;}
                    $branch = Branch::find($branchId);
                    $branchArticles = $branch->article_variations()->where('stock' , '>=', Setting::getZalandoMinQty());
                    $store = ($branch->zalando_id != null) ? $branch->zalando_id : $branch->wawi_number;
                    foreach($branchArticles->get() as $branchArticle) {
                        $arVar = $branchArticle->article_variation()->first();
                        $mainAr = $arVar->article()->first();
                        if($mainAr->active != '1') { continue; }
                        if($arVar->active != '1') { continue; }

                        $ArtProvider = ArticleProvider::where('fk_article_id','=', $mainAr->id)->where('fk_provider_id','=', $ZalandoProvider->id)->first();
                        if(!$ArtProvider){continue;} if($ArtProvider->active != 1){continue;}

                        //Filter für Marken
                        $brand = $mainAr->attributes()->where('name', '=', 'hersteller')->first();
                        if(!$brand || !in_array($brand->value, $brands)){ continue; }

                        if($arVar->getEan() == '' || $arVar->getStandardPrice() == null) { continue; }

                        if($arVar->min_stock != null && $arVar->min_stock != '' && $arVar->min_stock >= Setting::getZalandoMinQty()) {
                            if($branchArticle->stock < $arVar->min_stock) {
                                continue;
                            }
                        }
                        if($branchArticle->stock == 0) { continue; }

                        $wsPrice = $arVar->getWebStandardPrice();
                        $wdPrice = $arVar->getWebDiscountPrice();
                        $dPrice = $arVar->getDiscountPrice();

                        if($wdPrice != null && $wdPrice != '')
                        { $wawiDiscountPrice = number_format((float)str_replace(',','.',$wdPrice), 2, '.', ''); }
                        else if($dPrice != null && $dPrice != '')
                        {$wawiDiscountPrice = number_format((float)str_replace(',','.',$dPrice), 2, '.', '');}
                        else{$wawiDiscountPrice = false;}

                        if($wsPrice != null && $wsPrice != '') { $retailPrice = number_format((float)str_replace(',','.',$wsPrice), 2, '.', ''); }
                        else { $retailPrice = number_format((float)str_replace(',','.',$arVar->getStandardPrice()), 2, '.', ''); }

                        $currentPrice = $retailPrice;
                        $minPreisTyp=1;


                        // RABATTE
                        $continue = false; $Rabatt = 0;
                        switch($customer)
                        {
                            case "wunderschoen-mode":
                                $Rabatt = 0; $maxVKPreis= 34.90;
                                $checkRabatt = 11; $checked=0;
                                $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice && $checkThisPrice >= $maxVKPreis )
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice && $currentPrice >= $maxVKPreis){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    else{ $Rabatt = 0;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice && $checkThisPrice >= $maxVKPreis)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice && $currentPrice >= $maxVKPreis){ $Rabatt = $checkRabatt;$checked=1;}
                                    }else{ $Rabatt = 0;$checked=1; }
                                }
                            break;
                            case "modebauer":
                                //$Rabatt = 10;
                            break;
                            case "dhtextil":
                                $Rabatt = 0;
                                $checkRabatt = 5; $checked=0;
                                $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }

                                if($checked==0)
                                {
                                    if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                    {
                                        if(strtotime(date("d.m.Y")) >= strtotime("01.01.2021"))
                                        { $Rabatt = 0;}
                                        else{$Rabatt = $checkRabatt;}
                                    }
                                }
                            break;

                            case "vanhauth":
                                $Rabatt = 0;
                                $checkRabatt = 10; $checked=0;
                                $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }

                                if($checked==0)
                                {
                                    if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                    {
                                        if(strtotime(date("d.m.Y")) >= strtotime("01.01.2021"))
                                        { $Rabatt = 0;}
                                        else{$Rabatt = $checkRabatt;}
                                    }
                                }
                            break;

                            case "cosydh": $Rabatt = 5; break;

                            case "fruehauf":
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');


                                if($RabattSaison)
                                {
                                    switch($RabattSaison->value)
                                    {   case "1901":case "1902":case "1903":case "1904":case "1905":case "1906":case "1907":case "1908":
                                        case "1909":case "1910":case "1911":case "1912":case "1921":case "1922":case "2021":case "2001":
                                        case "2002":case "2003":case "2004":case "2005":
                                            $Rabatt=20.10;
                                            break;
                                        case "2121":case  "2122": case "2101": case "2102": case "2103": case "2104": case "2105" : case "2106":
                                        case "2107": case "2108": case "2109": case "2110":
                                            $Rabatt=5.10;
                                            break;
                                    }
                                }

                                /*
                                     Marc O’Polo, Saisons 2109, 2110, 2111 -10,1%
                                    Comma, Saisons 2101, 2102, 2103 -14,1%
                                    S’Oliver, Saisons 2101, 2102, 2103 -14,1%
                                    Marc Cain, Saison 2122 -10,1%
                                    TT Denim, Saison 2101, 2102, 2103, 2104 -10,1%
                                */
                                if($RabattSaison && $checkHersteller){
                                    switch($checkHersteller){
                                        case "MARCO´POLO":
                                            switch($RabattSaison->value){
                                                case "2109":case "2110":case "2111":
                                                    $Rabatt=10.1;
                                                    break;
                                            }
                                            break;
                                        case "COMMA":
                                            switch($RabattSaison->value){
                                                case "2101":case "2102":case "2103":
                                                    $Rabatt=14.1;
                                                    break;
                                            }
                                            break;
                                        case "S.OLIVER":
                                            switch($RabattSaison->value){
                                                case "2101":case "2102":case "2103":
                                                    $Rabatt=14.1;
                                                    break;
                                            }
                                            break;
                                        case "MARCCAIN":
                                            switch($RabattSaison->value){
                                                case "2122":
                                                    $Rabatt=20.1;
                                                    break;
                                            }
                                            break;
                                        case "TT DENIM":
                                            switch($RabattSaison->value){
                                                case "2101":case "2102":case "2103": case "2104":
                                                    $Rabatt=10.1;
                                                    break;
                                            }
                                            break;
                                    }
                                }

                                /*
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {
                                    case "MARCAUREL":
                                    case "MARCCAIN":
                                        $Rabatt = 30.1;
                                    break;

                                    default: $Rabatt = 20.1; break;
                                }
                                */
                            break;

                            case "mode-wittmann":


                                $Rabatt = 0;
                                $checkRabatt = 10;
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {   case "GANG":
                                    case "HERRLICHER":
                                    case "TAMARIS":
                                    case "MOSMOSH":  $Rabatt = $checkRabatt;  break;
                                    case "SOYACONCEP": case "BETTY & CO":case "CARTOON": $Rabatt=20.00;break;
                                }

                                    /*
                                if(strtotime(date("d.m.Y")) <= strtotime("06.04.2021"))
                                {
                                    $Rabatt = 0; $checkRabatt = 10;
                                    $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                    switch($checkHersteller)
                                    {   case "GANG":
                                        case "HERRLICHER":
                                        case "SOYACONCEPT":
                                        case "BETTY & CO":
                                        case "TAMARIS":
                                        case "MOSMOSH":  $Rabatt = $checkRabatt;  break;
                                    }
                                }
                                */

                            break;

                            case "schwoeppe":
                                $categories = $mainAr->categories()->get();
                                $waregroups=[];
                                foreach($categories as $category){
                                    $groups=$category->waregroups;
                                    foreach($groups as $group){
                                        $waregroups[]=$group;
                                    }
                                }
                                $categories=$waregroups;

                                $Warengruppen_ausgeschlossen = ['10023','10021'];

                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();

                                if($RabattSaison){switch($RabattSaison->value)
                                {   case "2121":
                                        $WareGroupsCheck = 0;
                                        if($categories){
                                            foreach($categories as $Cat){
                                                if($Cat && $Cat->wawi_number != null)
                                                {
                                                    if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;}
                                                }
                                            }
                                        }
                                        if($WareGroupsCheck==0){ $Rabatt = 10; }
                                    break;
                                    case "2022":
                                    case "2021":
                                        $WareGroupsCheck = 0;
                                        if($categories)
                                        {
                                            foreach($categories as $Cat)
                                            {   if($Cat && $Cat->wawi_number != null)
                                                {
                                                    if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen))
                                                    {
                                                        $WareGroupsCheck=1;
                                                    }
                                                }
                                        }
                                        }
                                        if($WareGroupsCheck==0){ $Rabatt = 30; }
                                    break;
                                    case "1921":
                                    case "1922":
                                    case "1821":
                                    case "1822":
                                    case "1721":
                                    case "1722":
                                    case "1621":
                                    case "1622":
                                    case "1521":
                                    case "1522":
                                        $WareGroupsCheck = 0;
                                        if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;} }  } }
                                        if($WareGroupsCheck==0){ $Rabatt = 50; }
                                    break;
                                }}

                                /*$hasRabattSaison = $mainAr->attributes()->where('name','=','fee-saisonbezeichnung')
                                ->where('value','=','Herbst/Winter 2020')->first();
                                if($hasRabattSaison){ $Rabatt = 20; }  */

                            break;

                            case "bstone":
                                $Rabatt = 0;
                                $categories = $mainAr->categories()->get();
                                //$Warengruppen_ausgeschlossen = ["10510", "10520", "10521", "21410", "21420", "21421" ];
                                $Warengruppen_rabatt = [
                                    '10000','10100','10200','10300','10400','10525'
                                    ,'11010','11020','11025','11030','11110','11115'
                                    ,'11120','11125','11130','11135','11140','11150','11230','11235',
                                    '11999','14000','14110','14210','14310','14414','19999','20000',
                                    '20100','20300','20410','20415','20500','20600','21010','21020',
                                    '21025','21030','21040','21110','21115','21120','21125',
                                    '21130','21135','21140','21150','21155','21160','21165',
                                    '21220','21225','21230','21310','21425','24000','24110',
                                    '24210','24310','24410','24411','24412','24413','24414',
                                    '29001','29010','29999','40000','40100','40200','41000','41300'
                                ];

                                $checkRabatt = 10; $checked=0;

                                $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        {   $WareGroupsCheck = 0;
                                            if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_rabatt)){$WareGroupsCheck=1;} }  } }
                                            if($WareGroupsCheck==1){ $Rabatt = $checkRabatt;$checked=1;  }
                                        }
                                        if(!$wawiDiscountPrice){
                                            $WareGroupsCheck = 0;
                                            if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_rabatt)){$WareGroupsCheck=1;} }  } }
                                            if($WareGroupsCheck==1){ $Rabatt = $checkRabatt;$checked=1;  }
                                        }
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        {   $WareGroupsCheck = 0;
                                            if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_rabatt)){$WareGroupsCheck=1;} }  } }
                                            if($WareGroupsCheck==1){ $Rabatt = $checkRabatt;$checked=1;  }
                                        }
                                        if(!$wawiDiscountPrice){
                                            $WareGroupsCheck = 0;
                                            if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_rabatt)){$WareGroupsCheck=1;} }  } }
                                            if($WareGroupsCheck==1){ $Rabatt = $checkRabatt;$checked=1;  }
                                        }
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }

                                if($checked==0)
                                {
                                    if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                    {
                                        if(strtotime(date("d.m.Y")) >= strtotime("01.01.2021"))
                                        { $Rabatt = 0;}
                                    }
                                }

                            break;

                            case "romeiks":
                                $Rabatt = 0;

                                /*
                                $checkHersteller_ausgeschlossen = $mainAr->getAttributeValueByKey('hersteller');

                                $checkRabatt = 30; $checked=0;

                                $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice || !$wawiDiscountPrice)
                                        {   switch($checkHersteller_ausgeschlossen)
                                            {   case "ZERRES":
                                                case "MAC": $Rabatt = 0;  break;
                                                default: $Rabatt = $checkRabatt; $checked=1; break;
                                            }
                                        }
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice || !$wawiDiscountPrice)
                                        {   switch($checkHersteller_ausgeschlossen)
                                            {   case "ZERRES":
                                                case "MAC": $Rabatt = 0;  break;
                                                default: $Rabatt = $checkRabatt; $checked=1; break;
                                            }
                                        }
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    { $Rabatt = 0;$checked=1; }
                                }

                                if($checked==0)
                                {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                    {   if(strtotime(date("d.m.Y")) >= strtotime("01.01.2021"))
                                        { $Rabatt = 0;}
                                    }
                                }
                                */
                                $Rabatt=10.00;

                            break;

                            case "pascha":
                                /*
                                $Rabatt=0.0;

                                //pauschal für Saison Februar 2021 22 Prozent Rabatt, erst einmal......
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->where('value','>=','2102')->first();
                                if($RabattSaison){
                                    $Rabatt=22.00;
                                }



                                $categories = $mainAr->categories()->get();
                                $waregroups=[];
                                foreach($categories as $category){
                                    $groups=$category->waregroups;
                                    foreach($groups as $group){
                                        $waregroups[]=$group;
                                    }
                                }
                                $categories=$waregroups;
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                if($categories){
                                    foreach($categories as $Cat){
                                        if($Cat && $Cat->wawi_number =='999'){
                                            if(strtotime($check_letzterwe) <= strtotime("28.02.2021"))  {
                                                $Rabatt=22.00;
                                            }
                                            elseif(strtotime($check_letzterwe) <= strtotime("31.03.2021"))  {
                                                $Rabatt=18.00;
                                            }
                                            elseif(strtotime($check_letzterwe) <= strtotime("15.05.2021"))  {
                                                $Rabatt=18.00;
                                            }
                                            else{
                                                $Rabatt=0.00;
                                            }
                                        }
                                    }
                                }
                                */


                                /*
                                    Somit möchte ich dann darum bitten, dass alle Artikel (alle Marken und alle Artikel) nach Wareneingangsdatum
                                    bis zum 28.02.2021 mit 22 Prozent Nachlass hinterlegt werden.

                                    Alle Artikel mit Wareneingangsdatum 01.03.2021 bis 31.03.201 bitte mit 17 Prozent Nachlass hinterlegen.

                                    Alle Artikel mit Wareneingangsdatum 01.04 2021 bis 30.04.2021 bitte mit 11 Prozent Nachlass hinterlegen

                                    Bitte alle anderen Rabattaktionen löschen.
                                */
                                $Rabatt=00.00;
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');

                                if(strtotime($check_letzterwe) <= strtotime("28.02.2021"))  {
                                    $Rabatt=22.00;
                                }
                                elseif(strtotime($check_letzterwe) <= strtotime("31.03.2021"))  {
                                    $Rabatt=17.00;
                                }
                                elseif(strtotime($check_letzterwe) <= strtotime("30.04.2021"))  {
                                    $Rabatt=11.00;
                                }
                                break;

                            case "plager":
                                $minPreisTyp=2;
                                $Rabatt = 0; $checkRabatt = 30; $ausgeschlossen=false;
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {   case "TRIUMPH":
                                    case "MEY D":
                                    case "HAJO":  $ausgeschlossen = true; break;
                                }
                                if(!$ausgeschlossen)
                                {
                                    $checkThisPrice = number_format( ($currentPrice - (($currentPrice / 100) * $checkRabatt)) , 2, '.', '');
                                    $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                    $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');

                                    if($check_letzterwe != "")
                                    {   if(strtotime($check_letzterwe) <= strtotime("01.11.2020"))
                                        {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice || !$wawiDiscountPrice)
                                            { $Rabatt = $checkRabatt;}
                                        }
                                    }
                                    if($check_letzterwe_var != "")
                                    {   if(strtotime($check_letzterwe_var) <= strtotime("01.11.2020"))
                                        {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice || !$wawiDiscountPrice)
                                            { $Rabatt = $checkRabatt;}
                                        }
                                    }
                                }
                            break;

                            case "velmo":
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) < strtotime("01.01.2021"))
                                    {
                                        continue 2;
                                    }
                                }


                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) < strtotime("01.01.2021"))
                                    {
                                        continue 2;
                                    }
                                }



                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');


                                if($checkHersteller=='MINOK'){
                                    if($RabattSaison)
                                    {
                                        if((int)$RabattSaison->value > 2101){
                                            continue 3;
                                        }

                                    }
                                }

                                if($checkHersteller=='STREET ONE'){
                                    if($RabattSaison)
                                    {
                                        switch($RabattSaison->value)
                                        {
                                            case "2101":case "2102": $Rabatt=30.00;break;
                                            case "2103":case "2104":case "2105":case "2106": $Rabatt=5.00;break;
                                        }
                                    }
                                }
                                if($checkHersteller=='CECIL'){
                                    if($RabattSaison)
                                    {
                                        switch($RabattSaison->value)
                                        {
                                            case "2101":case "2102": $Rabatt=33.00;break;
                                            case "2103":case "2104":case "2105":case "2106":  $Rabatt=5.00;break;
                                        }
                                    }
                                }
                                if($checkHersteller=='OPUS'){
                                    $Rabatt=5.00;
                                }
                                if($checkHersteller=='COMMA'){
                                    $Rabatt=5.00;
                                }
                                if($checkHersteller=='MAC'){
                                    $Rabatt=5.00;
                                }
                                if($checkHersteller=='SOYACONCEP'){
                                    $Rabatt=5.00;
                                }





                                break;

                            case "keller":
                                /*
                                    60 % Rabatt auf alle Artikel aus dem Jahr2020
                                -   also letzter WE 01.01.2020 bis 31.12.2020 -

                                    Alle anderen Artikel davor und ab 01.01.2021 ohne Rabatt
                                */
                                $Rabatt=0;

                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {
                                        if(strtotime($check_letzterwe) >= strtotime("01.01.2020"))
                                        {
                                            $Rabatt = 60;
                                        }
                                    }
                                }


                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {
                                        if(strtotime($check_letzterwe_var) >= strtotime("01.01.2020"))
                                        {
                                            $Rabatt = 60;
                                        }
                                    }
                                }
                            break;
                            case "pk-fashion":
                                $supplier_article=$mainAr->getAttributeValueByKey('hersteller-nr');
                                switch($supplier_article){
                                    case 140:case 141:
                                        $Rabatt=20.00;
                                        break;
                                }
                                break;
                            case "neheim":
                                /*
                                    alles was Wareneingang bis 30.11.2020 hat 22% und alle anderen Artikel erstmal  für Zalando um 11% reduzieren.

                                    bitte Lieferant 189, Mos Mosh aus der Aktion komplett rauslassen.
                                */

                                $Rabatt=0;

                                //Für Artikel vom Hersteller "MOS MOSH" gibt es keinen Rabatt
                                $supplier_article=$mainAr->getAttributeValueByKey('hersteller');
                                $do_break=false;
                                if($supplier_article=='MOS MOSH'){
                                    $do_break=true;
                                }
                                elseif($supplier_article=='GOLDGARN'){
                                    $do_break=true;
                                    $Rabatt=40.00;
                                }





                                //Die Überprüfung auf do-break verhindert eine Rabattsetzung
                                if(!$do_break){
                                    $Rabatt=11;
                                }

                                //Die Überprüfung auf do-break verhindert eine Rabattsetzung
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "" && !$do_break)
                                {   if(strtotime($check_letzterwe) <= strtotime("30.11.2020"))
                                    {
                                        $Rabatt = 22;
                                    }
                                }


                                //Die Überprüfung auf do-break verhindert eine Rabattsetzung
                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe_var != "" && !$do_break)
                                {   if(strtotime($check_letzterwe_var) <= strtotime("30.11.2020"))
                                    {
                                        $Rabatt = 22;
                                    }
                                }
                            break;

                            case "scheibe":
                                $Rabatt=10.00;

                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch(strtolower($checkHersteller) )
                                {   case "kennel":
                                    case "schmenger":
                                    case "on":  $Rabatt=0.0; break;
                                }

                                /*
                                $Rabatt = 10;
                                $categories = $mainAr->categories()->get();
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saisonbezeichnung')->first();
                                if($RabattSaison){switch($RabattSaison->value)
                                {
                                    case "HW 2020 2 Halbjahr":
                                    case "HW 2020 2. Halbjahr":
                                        $Rabatt = 40;
                                    break;
                                }}
                                */
                            break;

                            case "sparwel":
                                $Rabatt = 10;
                            break;

                            case "mehner":
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {
                                    case "GerryWeber" : $Rabatt = 30; break;
                                }
                            break;

                            case "fischer-stegmaier":
                                $minPreisTyp=1;
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');

                                // Hersteller Rabatte
                                $checked=false;
                                if( in_array($customer,$aktiveSALETenants) && $checkHersteller != "")
                                {   $ThisSlug = Str::slug($checkHersteller, '_');
                                    if($setting)
                                    {
                                        $settingId = $setting->id;
                                        $thisHerstellerSalePercent = Settings_Attribute::where('fk_setting_id', '=', $settingId)
                                        ->where('name', '=', 'za_'.'cr_sale_percent_'.$ThisSlug)->first();
                                        if($thisHerstellerSalePercent)
                                        {
                                            /*
                                                Für die Hersteller Hugo Boss und Miriade müssen wir noch den Mindeswert beachten.
                                            */
                                            if($thisHerstellerSalePercent->value=="za_cr_sale_percent_hugo_boss"){
                                                $thisHerstellerSalePercentValue = number_format((float)str_replace(',','.',$thisHerstellerSalePercent->value), 2, '.', '');
                                                if($currentPrice >= 39.90){
                                                    if($thisHerstellerSalePercentValue > 0)
                                                    {
                                                        $checked=1; $Rabatt = $thisHerstellerSalePercentValue;
                                                    }
                                                }
                                            }
                                            elseif($thisHerstellerSalePercent->value=="za_cr_sale_percent_miriade"){
                                                $thisHerstellerSalePercentValue = number_format((float)str_replace(',','.',$thisHerstellerSalePercent->value), 2, '.', '');
                                                if($currentPrice >= 49.90){
                                                    if($thisHerstellerSalePercentValue > 0)
                                                    {
                                                        $checked=1; $Rabatt = $thisHerstellerSalePercentValue;
                                                    }
                                                }
                                            }
                                            else{
                                                $thisHerstellerSalePercentValue = number_format((float)str_replace(',','.',$thisHerstellerSalePercent->value), 2, '.', '');
                                                if($thisHerstellerSalePercentValue > 0)
                                                { $checked=1; $Rabatt = $thisHerstellerSalePercentValue; }
                                            }



                                        }
                                    }
                                }
                                if($checked)
                                {   $Saison_checked=false;
                                    $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                    if($RabattSaison){switch($RabattSaison->value)
                                    {   case "0025":
                                            $Saison_checked=true; $Rabatt = 20.1;
                                        break;
                                    }}
                                    if($Saison_checked){}
                                }
                                // Zusatzcheck Saison
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                if($RabattSaison){switch($RabattSaison->value)
                                {   case "0021": $Rabatt = 20.1;  break; }}


                            break;

                            case "senft":
                                /*
                                if($currentPrice>=49){$Rabatt = 10.11;}
                                if($currentPrice>=69){$Rabatt = 20.11;}
                                if($currentPrice>=119){$Rabatt = 30.11;}
                                */


                                //strtotime(date('Y-m-d'))
                                $Rabatt=0.00;
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');

                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {
                                        if($currentPrice>=59){$Rabatt = 11;}
                                        if($currentPrice>=99){$Rabatt = 21;}
                                        if($currentPrice>=119){$Rabatt = 30;}
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    {   $Rabatt = 0;
                                        if($currentPrice>=59){$Rabatt = 5;}
                                        //if($currentPrice>=99){$Rabatt = 11;}
                                    }
                                }

                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {
                                        if($currentPrice>=59){$Rabatt = 11;}
                                        if($currentPrice>=99){$Rabatt = 21;}
                                        if($currentPrice>=119){$Rabatt = 30;}
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    {   $Rabatt = 0;
                                        if($currentPrice>=59){$Rabatt = 5;}
                                        //if($currentPrice>=99){$Rabatt = 11;}
                                    }
                                }
                            break;
                        }

                        if($continue){continue;}





                        if($Rabatt > 0)
                        {
                            $currentPrice = number_format( ($currentPrice - (($currentPrice / 100) * $Rabatt)) , 2, '.', '');
                        }

                        if($wawiDiscountPrice && $wawiDiscountPrice < $currentPrice)
                        { $currentPrice = $wawiDiscountPrice;}

                        // gobalen min. Preis Prüfen

                        if($minPreisTyp==1){if($retailPrice < $minPrice) { continue;}}
                        if($minPreisTyp==2)
                        {   //Preisfilter mindest Preis
                            if($currentPrice < $minPrice)
                            {   if($retailPrice > $minPrice){$currentPrice = $retailPrice;}
                                else{continue;}
                            }
                        }

                        // Hersteller min. Preise prüfen
                        $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                        if( in_array($customer,$aktiveSALETenants) && $checkHersteller != "")
                        {  if($setting)
                            {   $settingId = $setting->id;
                                $ThisSlug = Str::slug($checkHersteller, '_');
                                $thisHerstellerMinPreis = Settings_Attribute::where('fk_setting_id', '=', $settingId)
                                ->where('name', '=', 'za_'.'cr_min_price_'.$ThisSlug)->first();
                                if($thisHerstellerMinPreis)
                                {   $HerstellerMinPreisValue = number_format((float)str_replace(',','.',$thisHerstellerMinPreis->value), 2, '.', '');

                                    if($minPreisTyp==1){if($retailPrice < $HerstellerMinPreisValue) { continue;}}
                                    if($minPreisTyp==2)
                                    {   //Preisfilter mindest Preis
                                        if($currentPrice < $HerstellerMinPreisValue)
                                        {   if($retailPrice > $HerstellerMinPreisValue){$currentPrice = $retailPrice;}
                                            else{continue;}
                                        }
                                    }
                                }
                            }
                        }

                        $items[] = [
                            'store' => $store,
                            'ean' => $arVar->getEan(),
                            'price' => $currentPrice,
                            'retail_price' => $retailPrice,
                            'quantity' => $branchArticle->stock,
                            'article_number' => $arVar->getEan(),
                            'product_number' => $mainAr->number,
                            'product_name' => $mainAr->name,
                            'article_color' => $arVar->getColorText(),
                            'article_size' => $arVar->getSizeText()
                        ];
                        $articleCount++;
                    }
                }
                if(count($items)>0)
                {
                    if(file_exists($filePath.$date."zalandoupdate.csv")) {
                        unlink($filePath.$date."zalandoupdate.csv");
                    }

                    $fileptr = fopen($filePath.$date."zalandoupdate.csv","a"); // open the file
                    fputs(
                        $fileptr,
                        'store'.";".
                        'ean'.";".
                        'price'.";".
                        'retail_price'.";".
                        'quantity'.";".
                        'article_number'.";".
                        'product_number'.";".
                        'product_name'.";".
                        'article_color'.";".
                        'article_size'."\n"
                    );
                    foreach($items as $item) {
                        fputs(
                            $fileptr,
                            '"'.$item['store'].'"'.";".
                            '"'.$item['ean'].'"'.";".
                            $item['price'].";".
                            $item['retail_price'].";".
                            $item['quantity'].";".
                            '"'.$item['article_number'].'"'.";".
                            '"'.$item['product_number'].'"'.";".
                            $item['product_name'].";".
                            $item['article_color'].";".
                            $item['article_size']."\n"
                        );
                    }
                    fclose($fileptr); // closes the file at $fileptr.

                    try{
                        $req = $client->put(
                            $url.'/'.$credentials['client_id'].'/'.$date.'zalandoupdate.csv',
                            [
                                'headers' => [
                                    'X-Api-Key' => $credentials['api_key'],
                                    'Content-Type' => 'text/csv'
                                ],
                                'body' => fopen($filePath.$date.'zalandoupdate.csv', 'r')
                            ],
                        );
                        Log::channel('single')->info('Zalando-Export '.$customer.':'.$req->getStatusCode());
                    }
                    catch(GuzzleException $e) {
                        Log::error($e->getMessage());
                        if($synchro) {
                            $synchro->expected_count = $articleCount;
                            $synchro->fk_synchro_status_id = $errorSynchroS->id;
                            $synchro->end_date = date('Y-m-d H:i:s');
                            $synchro->add_data = $e->getMessage();
                            $synchro->filepath = $customer."/csv_zalando/".$date."zalandoupdate.csv";
                            $synchro->save();
                        }
                    }

                    if($synchro) {
                        $synchro->expected_count = $articleCount;
                        $synchro->success_count = $articleCount;
                        $synchro->failed_count = $error_count;
                        $synchro->fk_synchro_status_id = $successSynchroS->id;
                        $synchro->end_date = date('Y-m-d H:i:s');
                        $synchro->filepath = $customer."/csv_zalando/".$date."zalandoupdate.csv";
                        $synchro->save();
                    }

                }

            }
        }
    }
}
