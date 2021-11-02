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

class ExportZalandoCSV_test extends Command
{
    protected $signature = 'export:zalandocsv_test {customer}';

    protected $description = 'Creates and exports a CSV to Zalando Connected Retail';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {   $checkONE = 0; $SamePriceCount=0;
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}

        $customers = Storage::disk('customers')->directories();

        $tenants = Tenant::all();
        $tenantTeams = [];foreach ($tenants as $tenant) { $tenantTeams[] = $tenant->subdomain;}

        $aktiveSALETenants = ['fischer-stegmaier','basic']; // Für Hersteller Rabatte

        foreach($customers as $customer)
        {
            if(!in_array($customer, $tenantTeams)) { continue;  }
            if($exec_for_customer && $exec_for_customer != $customer){continue;}
            echo "Beginne für Customer: ".$customer."\n";

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

                /*$credentials = Setting::getZalandoCRCredentials();
                if($credentials['client_id'] == null || $credentials['api_key'] == null)
                { continue; }*/

                $settingType = Settings_Type::where('name','=', 'partner')->first();
                $setting = Setting::where('fk_settings_type_id','=',$settingType->id)->first();
                $date = date('YmdHis');

                $articleCount = 0;

                $filePath = storage_path()."/customers/".$customer."/csv_zalando_test/";
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
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {
                                    case "MARCAUREL":
                                    case "MARCCAIN":
                                        $Rabatt = 30.1;
                                    break;

                                    default: $Rabatt = 20.1; break;
                                }
                            break;

                            case "mode-wittmann":
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                if($RabattSaison){
                                switch($RabattSaison->value)
                                {   case "2001": $Rabatt = 30; break;
                                    case "2002": $Rabatt = 25; break;
                                }}
                            break;

                            case "schwoeppe":
                                $categories = $mainAr->categories()->get();
                                $Warengruppen_ausgeschlossen = ['10023','10021'];

                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                if($RabattSaison){switch($RabattSaison->value)
                                {   case "2121":
                                        $WareGroupsCheck = 0;
                                        if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;} }  } }
                                        if($WareGroupsCheck==0){ $Rabatt = 10; }
                                    break;
                                    case "2022":
                                    case "2021":
                                        $WareGroupsCheck = 0;
                                        if($categories){   foreach($categories as $Cat){   if($Cat && $Cat->wawi_number != null) { if(in_array($Cat->wawi_number,$Warengruppen_ausgeschlossen)){$WareGroupsCheck=1;} }  } }
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

                            case "pascha":
                                /*
                                $Rabatt = 0;
                                $checkRabatt = 15; $checked=0;
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
                                    { $Rabatt = 3;$checked=1; }
                                }
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {   if($wawiDiscountPrice && $wawiDiscountPrice >= $checkThisPrice)
                                        { $Rabatt = $checkRabatt;$checked=1;}
                                        if(!$wawiDiscountPrice){ $Rabatt = $checkRabatt;$checked=1;}
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    { $Rabatt = 3;$checked=1; }
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
                                */

                                $Rabatt=0.0;
                                //Warengruppe 007
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
                                        if($Cat && $Cat->wawi_number =='007')
                                        {
                                            if(strtotime($check_letzterwe) <= strtotime("01.03.2021"))  {
                                                $Rabatt=22.00;
                                            }
                                            else {
                                                $Rabatt=11.00;
                                            }
                                        }

                                    }
                                }

                                //Saison 1 und 2
                                $RabattSaison = $mainAr->attributes()->where('name','=','fee-saison')->first();
                                switch($RabattSaison->value){
                                    case 1: case 2:
                                        $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                        $checkHersteller=strtolower($checkHersteller);
                                        if($checkHersteller=='street one' || $checkHersteller=='cecil'){
                                            $Rabatt=19.00;
                                        }

                                        break;
                                }

                                //Saison Februar 2021 und älter pauschal 22 Prozent
                                if($RabattSaison->value >= 2102){
                                    $Rabatt=22.00;
                                }

                                if(!$Rabatt){
                                    $Rabatt=10.00;
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

                            case "keller":
                                /*if($mainAr->has('categories'))
                                {   $categories = $mainAr->categories()->get();
                                    if($categories)
                                    {   $r_set=false;
                                        foreach($categories as $Cat)
                                        {   if($Cat && $Cat->wawi_number != null)
                                            {
                                                if($Cat->wawi_number == "00280"){$r_set=true; $Rabatt = 45; }
                                            }
                                        }
                                        if(!$r_set){$Rabatt = 25;}
                                    }
                                }*/
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {
                                    case "MORE&MORE":
                                    case "LYMAN INC":
                                    case "HEARTKISS":
                                    case "GEISHA":
                                    case "FRANSA":
                                    case "RE.DRAFT":
                                    case "SMITH&SOUL":
                                    case "OPUS":  $Rabatt = 50; break;
                                    case "s.Oliver": $Rabatt = 57; break;
                                }
                            break;

                            case "scheibe":
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');
                                switch($checkHersteller)
                                {
                                    case "BIRKENSTOC":
                                    case "LA STRADA":  $Rabatt = 8; break;
                                    case "KENNEL& SC":
                                        case "LOWA":  $Rabatt = 5; break;
                                        case "ON SHOES":  $Rabatt = 6; break;
                                }
                            break;

                            /*case "hl":
                                if($mainAr->has('categories'))
                                {   $categories = $mainAr->categories()->get();
                                    if($categories)
                                    {   foreach($categories as $Cat)
                                        {   if($Cat && $Cat->wawi_number != null)
                                            {
                                                if($Cat->wawi_number == "00009"
                                                || $Cat->wawi_number == "00013"){ $Rabatt = 26; }
                                            }
                                        }
                                    }
                                }
                            break;*/

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
                            case "basic":
                            case "fischer-stegmaier":
                                $minPreisTyp=1;
                                $checkHersteller = $mainAr->getAttributeValueByKey('hersteller');

                                /*if(strtotime(date('Y-m-d H:i:s')) <= strtotime("30.11.2020 23:59:59"))
                                {
                                    if(strtotime(date('Y-m-d H:i:s')) <= strtotime("25.11.2020 23:59:59"))
                                    {
                                        switch($checkHersteller)
                                        {
                                            case "NA-KD" :
                                            case "SOYACONCEP" :
                                            case "LEVIS" :
                                            case "BESTSELLER" :
                                                $Rabatt = 20.1;
                                            break;
                                        }
                                    }
                                    if(strtotime(date('Y-m-d H:i:s')) >= strtotime("26.11.2020 00:00:01"))
                                    { $Rabatt = 20.1; }
                                }
                                if(strtotime(date('Y-m-d H:i:s')) >= strtotime("01.12.2020 00:00:01"))
                                { $Rabatt = 10.1; }*/

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
                                        {   $thisHerstellerSalePercentValue = number_format((float)str_replace(',','.',$thisHerstellerSalePercent->value), 2, '.', '');
                                            if($thisHerstellerSalePercentValue > 0)
                                            { $checked=1; $Rabatt = $thisHerstellerSalePercentValue; }
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
                            break;

                            case "senft":
                                if($currentPrice>=49){$Rabatt = 10.11;}
                                if($currentPrice>=69){$Rabatt = 20.11;}
                                if($currentPrice>=119){$Rabatt = 30.11;}
                                //strtotime(date('Y-m-d'))
                                $check_letzterwe = $mainAr->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe != "")
                                {   if(strtotime($check_letzterwe) <= strtotime("31.12.2020"))
                                    {
                                        if($currentPrice>=49){$Rabatt = 11;}
                                        if($currentPrice>=69){$Rabatt = 21;}
                                        if($currentPrice>=119){$Rabatt = 30;}
                                    }
                                    if(strtotime($check_letzterwe) >= strtotime("01.01.2021"))
                                    {
                                        if($currentPrice>=59){$Rabatt = 5;}
                                        if($currentPrice>=99){$Rabatt = 11;}
                                    }
                                }

                                $check_letzterwe_var = $arVar->getAttributeValueByKey('fee-letzterwe');
                                if($check_letzterwe_var != "")
                                {   if(strtotime($check_letzterwe_var) <= strtotime("31.12.2020"))
                                    {
                                        if($currentPrice>=49){$Rabatt = 11;}
                                        if($currentPrice>=69){$Rabatt = 21;}
                                        if($currentPrice>=119){$Rabatt = 30;}
                                    }
                                    if(strtotime($check_letzterwe_var) >= strtotime("01.01.2021"))
                                    {
                                        if($currentPrice>=59){$Rabatt = 5;}
                                        if($currentPrice>=99){$Rabatt = 11;}
                                    }
                                }
                            break;
                        }

                        if($continue){continue;}





                        if($Rabatt > 0)
                        { $currentPrice = number_format( ($currentPrice - (($currentPrice / 100) * $Rabatt)) , 2, '.', ''); }

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
                        $articleCount++; echo ".";
                    }
                }
                if(count($items)>0){echo "\nArtikel Count: ".$articleCount."\n";  } // ."gleicher Preis Count: ".$SamePriceCount
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



                }

            }
        }
    }
}
