<?php

namespace App\Http\Controllers\Tenant\Providers\Fee;

use App\Http\Controllers\Controller;
use App\Tenant, App\Tenant\Branch, App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Article_Eigenschaften, App\Tenant\Article_Eigenschaften_Articles,App\Tenant\Article_Eigenschaften_Data,App\Tenant\Article_Eigenschaften_Categories;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Provider_Type;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article_Variation_Image;
use App\Tenant\Article_Variation_Image_Attribute;
use App\Tenant\WaWi;
use App\Tenant\Attribute_Set;
use Storage, Config;
use App\Http\Controllers\Tenant\Providers\Fee\FeeController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use App\Http\Controllers\Tenant\Providers\Shopware\ShopwareAPIController;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Log;

class FeeController extends Controller
{

    private $shops = [];

    public function __construct() {
        //parent::__construct();
    }

    public function importFeeCsv($customer = false, $EchologAktiv = false)
    {
        $exec_for_customer = $customer;
        if(!$exec_for_customer||$exec_for_customer=="false"||$exec_for_customer==""){return;}

        $FarbCodes = [
            'beige','anthrazit','hellblau','hellgrau','schlamm','cognac','camel','ecru','dunkelblau','oliv'
            ,'dunkelbraun','blau','braun','bronze','gelb','gold','grau','grün','lila','orange'
            ,'rosa','rot','schwarz','silber','weiss','mehrfarbig'
        ];

        $EUGroessenCodes = [
            'XS','S','M','L','XL','XXL','3XL'
        ];



        //CSV Fields
        $STA_ID = 0;$STG_ID = 1;$ARTIKELNR = 2;
        $FARBNR = 3;$LAENGE = 4;$LST_ID = 5;$GROESSE = 6;$FIL_ID = 7;$FILIALE = 8;
        $FIL_BESCHREIBUNG = 9;$SAI_ID = 10;$SAISON = 11;$WAR_ID = 12;$STUECK = 13;$VK = 14;
        $LIEFERANT = 15;$SAISONBEZEICHNUNG = 16;$WARENGRUPPE = 17;
        $WARENGRUPPENBEZEICHNUNG = 18;$LIEFARTIKELBEZEICHNUNG = 19;
        $EIGENEARTIKELNR = 20;$KOLLEKTION = 21;
        $INFO1 = 22;$INFO2 = 23;$INFO3 = 24;$INFO4 = 25;$INFO5 = 26;
        $BILD1 = 27;$BILD2 = 28;$BILD3 = 29;$BILD4 = 30;$BILD5 = 31;
        $EANNR = 32;$VKWEB = 33; $WAR_KURZBEZEICHNUNG = 34; $LETZTERWARENEINGANG = 35;

        $customers = Storage::disk('customers')->directories();

        $articleNameStaID = ['visc'];
        $articleNameColor = ['demo2','stilfaktor'];
        $tenants = Tenant::all();
        $secondWawi = []; /*'wunderschoen-mode'*/
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
            ,'cosydh','keller', 'haider', 'hl', 'sparwel','neheim',
            'mehner',
            'schwoeppe',
            'plager',
            'favors','fashionobermann','scheibe','modebauer', 'bstone', 'frauenzimmer','olgasmodewelt'
            ,'romeiks','velmo','4youjeans'
        ];

         if($exec_for_customer && $exec_for_customer != "")
         { $deltaCustomers = [$exec_for_customer]; }

        $folderName = 'feecsv';
        $customerFolders = Storage::disk('customers')->directories($customer);
        if(in_array($customer.'/'.$folderName, $customerFolders))
        {
            $files = Storage::disk('customers')->files($customer.'/'.$folderName);
            $files = preg_grep('/^'.$customer.'\/feecsv\/visticleshop/', $files);

            //Continue if no files by fee
            if(empty($files)) {
                echo 'keine Dateien';
                return; }

            //Sort files to process oldest first
            usort($files, function($b, $a) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });
            //the most recent file to process
            $fileName = $files[0];

            if($EchologAktiv){echo "\n"."File: ".$fileName;}

            // check is part file or original
            $PartFile = strpos($fileName, "_PART_");
            if($PartFile)
            {
                $ThisPartOrgName = $fileName;
                $pos = min(strpos($ThisPartOrgName, "_PART_"), strlen($ThisPartOrgName));
                $ThisPartOrgName = substr($ThisPartOrgName, 0, $pos);
                $OrgFileDate = Storage::disk('customers')->lastModified($customer.'/feecsv_backup/articles-'.basename($ThisPartOrgName, ".csv").'.csv');

            }
            //check ob die aktuelle Datei älter ist als das letzte Bestandsupdate
            $DeltaRepeat = false;
            $Deltafiles = Storage::disk('customers')->files($customer.'/feecsv_backup/');
            $Deltafiles = preg_grep("/"."Deltavisticleshop"."/", $Deltafiles);
            usort($Deltafiles, function($b, $a) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });
            $currentFileDate = Storage::disk('customers')->lastModified($fileName);
            if(!$PartFile)
            {   $OrgFileDate = $currentFileDate;

                /*// alle zu verarbeitenden Artikel der Parts vom Bestand auf Null setzen
                if(!in_array($customer, $secondWawi)){
                    $notProcessed = Article_Variation::with(['branches'])
                    ->whereHas('article', function($query) {
                        $wawi = WaWi::where('name', '=', 'FEE')->first();
                        $query->where('fk_wawi_id', '=', $wawi->id);
                    })->get();
                    foreach($notProcessed as $notProcessedVar) {
                        $branches = $notProcessedVar->branches()->get();
                        foreach($branches as $branch)
                        { $branch->stock = 0; $branch->save(); }
                    }
                }*/
            }
            if($Deltafiles && count($Deltafiles)>0)
            {   $DeltaFileDate = Storage::disk('customers')->lastModified($Deltafiles[0]);
                if($DeltaFileDate > $currentFileDate || $DeltaFileDate > $OrgFileDate)
                {   // Deltafile muß nochmal durchlaufen werden
                    $DeltaRepeat = $Deltafiles[0];
                }
            }
            if($EchologAktiv){echo " "."PartFile: ".$PartFile." "."DeltaRepeat: ".$DeltaRepeat;}
            if(!$this->checkTransferState($fileName)) { return; }
            //Create temp file and write recent file content
            $processTemp = tmpfile();
            try { fwrite($processTemp, Storage::disk('customers')->get($fileName)); } catch (FileNotFoundException $e) { return; }


                $backup_date = date('Y-m-d-H-i-s');
                $backupFilePath = "";
                //Save Backup aktuelle Datei
                if(!Storage::disk('customers')->exists($customer.'/feecsv_backup/articles-'.basename($fileName, ".csv").'.csv'))
                {   try { Storage::disk('customers')->copy($fileName, $customer.'/feecsv_backup/articles-'.basename($fileName, ".csv").'.csv'); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }
                    $backupFilePath = $customer.'/feecsv_backup/articles-'.basename($fileName, ".csv").'.csv';
                }else
                {   try { Storage::disk('customers')->copy($fileName, $customer.'/feecsv_backup/articles-'.basename($fileName, ".csv")."_".$backup_date.'.csv'); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }
                    $backupFilePath = $customer.'/feecsv_backup/articles-'.basename($fileName, ".csv")."_".$backup_date.'.csv';
                }

                foreach($files as $thisfileName)
                {   if($thisfileName == $fileName) { continue; }
                    if(!$this->checkTransferState($thisfileName)) { continue; }
                    $thisPartFile = strpos($thisfileName, "_PART_");
                    $thisUmsatzFile = strpos($thisfileName, ".orc");
                    if($thisPartFile ||  $thisUmsatzFile) { continue; }
                    $thisFileDate = Storage::disk('customers')->lastModified($thisfileName);
                    if($thisFileDate < $currentFileDate)
                    {
                        //Save Backup & delete andere Importe
                        if(!Storage::disk('customers')->exists($customer.'/feecsv_backup/articles-'.basename($thisfileName, ".csv").'.csv'))
                        { try { Storage::disk('customers')->copy($thisfileName, $customer.'/feecsv_backup/articles-'.basename($thisfileName, ".csv").'.csv'); } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); } }
                        else
                        { try { Storage::disk('customers')->copy($thisfileName, $customer.'/feecsv_backup/articles-'.basename($thisfileName, ".csv")."_".$backup_date.'.csv');  } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); } }

                        try { Storage::disk('customers')->delete($thisfileName); } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); }
                    }
                }
                try { Storage::disk('customers')->delete($fileName); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }


                // Check File Size
                $maxSize = 2;//2 Mb
                $puffer = 0.4; $part = 1;
                $size = filesize ( stream_get_meta_data($processTemp)['uri'] ) / 1000000;
                if ($size  > $maxSize+$puffer)
                {
                    $fopen = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                    $counter=0;$firstline;
                    while (($line = fgetcsv($fopen, 0, ";")) !== FALSE)
                    {   if($counter==0){$firstline = $line;}$counter++;
                        $path = $customer.'/feecsv/'.basename($fileName, ".csv")."_PART_".$part."".'.csv';
                        $ftowrite = (Storage::disk('customers')->exists($path))? fopen(Storage::disk('customers')->path('/').$path, "a") : fopen(Storage::disk('customers')->path('/').$path, "w");
                        fputcsv($ftowrite,$line, ";");
                        clearstatcache();
                        $size = filesize ( Storage::disk('customers')->path('/').$path ) / 1000000;
                        if ($size  > $maxSize)
                        {	fclose($ftowrite);
                            $part++;
                            if($part>1)
                            {	$path = $customer.'/feecsv/'.basename($fileName, ".csv")."_PART_".$part."".'.csv';
                                $ftowrite = (Storage::disk('customers')->exists($path))? fopen(Storage::disk('customers')->path('/').$path, "a") : fopen(Storage::disk('customers')->path('/').$path, "w");
                                fputcsv($ftowrite,$firstline, ";");
                                fclose($ftowrite);
                            }
                        }
                    }
                    fclose($fopen);
                }


            //Set DB Connection
            \DB::purge('tenant');
            $tenant = $tenants->where('subdomain','=', $customer)->first();

            $config = Config::get('database.connections.tenant');
            $config['database'] = $tenant->db;
            $config['username'] = $tenant->db_user;
            $config['password'] = decrypt($tenant->db_pw);
            config()->set('database.connections.tenant', $config);

            \DB::connection('tenant');

            // Filter Gruppen aussondern
            $AttrSetGroups = [];
            $AttrSetGroups = Attribute_Set::where('id', '=', '1')->with('groups')->get();
            if(isset($AttrSetGroups[0])){$AttrSetGroups = $AttrSetGroups[0]->groups;}
            $AttrSetFilterGroups = [];
            foreach($AttrSetGroups as $AttrGroup)
            {   if($AttrGroup->is_filterable == 1)
                {
                    if($AttrGroup->name == "Farbe"){ $AttrSetFilterGroups['colors'] = $AttrGroup->id; }
                    if($AttrGroup->name == "Größe"){ $AttrSetFilterGroups['sizes'] = $AttrGroup->id; }
                    if($AttrGroup->name == "Länge"){ $AttrSetFilterGroups['lengths'] = $AttrGroup->id; }
                }
            }

            // Prüfe ob Größen Eigenschaften bereits existieren
            $AlleEigenschaften = Article_Eigenschaften::get();
            $GroessenEigenschaften=[ 'Kragenweite' => false ,'Konfektion' => false ,'Jeansgröße' => false ,'Schuhgröße' => false ,'Gürtellänge' => false, 'EU-Größen' => false ];
            if($AlleEigenschaften)
            {   foreach($AlleEigenschaften as $Eigenschaft)
                {   switch($Eigenschaft->name)
                    {   case "Kragenweite": $GroessenEigenschaften["Kragenweite"]=$Eigenschaft; break;
                        case "Konfektion": $GroessenEigenschaften["Konfektion"]=$Eigenschaft; break;
                        case "Jeansgröße": $GroessenEigenschaften["Jeansgröße"]=$Eigenschaft; break;
                        case "Schuhgröße": $GroessenEigenschaften["Schuhgröße"]=$Eigenschaft; break;
                        case "Gürtellänge": $GroessenEigenschaften["Gürtellänge"]=$Eigenschaft; break;
                        case "EU-Größen": $GroessenEigenschaften["EU-Größen"]=$Eigenschaft; break;
                    }
                }
                if($GroessenEigenschaften["Kragenweite"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'Kragenweite','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["Kragenweite"] = $newEigenschaft; }
                if($GroessenEigenschaften["Konfektion"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'Konfektion','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["Konfektion"] = $newEigenschaft; }
                if($GroessenEigenschaften["Jeansgröße"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'Jeansgröße','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["Jeansgröße"] = $newEigenschaft; }
                if($GroessenEigenschaften["Schuhgröße"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'Schuhgröße','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["Schuhgröße"] = $newEigenschaft; }
                if($GroessenEigenschaften["Gürtellänge"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'Gürtellänge','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["Gürtellänge"] = $newEigenschaft; }
                if($GroessenEigenschaften["EU-Größen"]==false) { $newEigenschaft = Article_Eigenschaften::updateOrCreate(['name' => 'EU-Größen','is_filterable' => 1,'active' => 1]); $GroessenEigenschaften["EU-Größen"] = $newEigenschaft; }
            }


            $providers = Provider::all();
            $synchroType = Synchro_Type::where('key','=','fee_import_csv')->first();
            $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
            $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
            $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
            $hasSynchro = true; $synchro = null; $processDate = date('YmdHis');
            $batchCount = 0; $batchItemCount = 0;
            $processedVariationIds = []; $processedEANs = [];
            if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS) { $hasSynchro = false; }
            if($hasSynchro && !$PartFile)
            {
                $synchro = Synchro::create(
                    [
                        'fk_synchro_type_id' => $synchroType->id,
                        'fk_synchro_status_id' => $inProgressSynchroS->id,
                        'start_date' => date('Y-m-d H:i:s')
                    ]
                );
                if($part>1)
                {   // wenn CSV Split stattgefunden hat, Sync Download Link setzen
                    if($synchro) {
                        $synchro->filepath = $backupFilePath;
                        $synchro->save();
                    }
                    // alle zu verarbeitenden Artikel der Parts vom Bestand auf Null setzen
                    if(!in_array($customer, $secondWawi)){
                        $notProcessed = Article_Variation::with(['branches'])
                        ->whereHas('article', function($query) {
                            $wawi = WaWi::where('name', '=', 'FEE')->first();
                            $query->where('fk_wawi_id', '=', $wawi->id);
                        })->get();
                        foreach($notProcessed as $notProcessedVar) {
                            $branches = $notProcessedVar->branches()->get();
                            foreach($branches as $branch)
                            { $branch->stock = 0; $branch->save(); }
                        }
                    }
                    // ersten Part gleich verarbeiten //$exitCode = Artisan::call('import:feecsv_startjob_kunde', ['customer' => $customer]);
                    return;
                }
            }


            //Read File Content
            $file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
            $row = 0;

            Log::channel('single')->info("FeeCSV Import - ".$customer." - ".$fileName);
            if($EchologAktiv){echo "\n"."Beginne Log";}
            while (($data = fgetcsv($file_content, 0, ";")) !== FALSE)
            {
                $row++; if($row == 1) { continue; }
                try {
                    //Branch
                    $branch = Branch::updateOrCreate(
                        [   'wawi_ident' => "fee-".$data[$FIL_ID] ],
                        [   'name' => $data[$FIL_BESCHREIBUNG],
                            'active' => 1,
                            'wawi_number' => $data[$FILIALE]
                        ]
                    );

                    //Articles
                    $article_name = $data[$ARTIKELNR];
                    if(in_array($customer, $articleNameStaID))
                    { $article_name .= "_".$data[$STA_ID]; }

                    if(in_array($customer, $articleNameColor))
                    {   $thisString = $article_name.'--'.$data[$FARBNR];
                        if(strpos($thisString, "ä")){$thisString=str_replace("ä", "AE", $thisString);}
                        if(strpos($thisString, "Ä")){$thisString=str_replace("Ä", "AE", $thisString);}
                        if(strpos($thisString, "ö")){$thisString=str_replace("ö", "OE", $thisString);}
                        if(strpos($thisString, "Ö")){$thisString=str_replace("Ö", "OE", $thisString);}
                        if(strpos($thisString, "ü")){$thisString=str_replace("ü", "UE", $thisString);}
                        if(strpos($thisString, "Ü")){$thisString=str_replace("Ü", "UE", $thisString);}
                        if(strpos($thisString, "(")){$thisString=str_replace("(", "", $thisString);}
                        if(strpos($thisString, ")")){$thisString=str_replace(")", "", $thisString);}
                        if(strpos($thisString, " ")){$thisString=str_replace(" ", "-", $thisString);}
                        if(strpos($thisString, "\u00df")){$thisString=str_replace("\u00df", "SS", $thisString);}
                        if(strpos($thisString, "ß")){$thisString=str_replace("ß", "SS", $thisString);}

                        $article_name = $thisString;
                    }

                    $article = Article::where('vstcl_identifier','=', "vstcl-".$article_name)->where('fk_wawi_id', '=', 1)
                    ->where('name', '=', $data[$EIGENEARTIKELNR])->where('ean', '=', $data[$EANNR])
                    ->where('number', '=', $article_name)->where('fk_attributeset_id', '=', 1 )->first();
                    // wenns den Artikel schon gibt, keine Änderung, sonst UpdateCreate
                    if(!$article)
                    {   $article = Article::updateOrCreate(
                            [   'vstcl_identifier' => "vstcl-".$article_name, 'fk_wawi_id' => 1 ],
                            [   'name' => $data[$EIGENEARTIKELNR],
                                'ean' => $data[$EANNR],
                                'number' => $article_name,
                                //'batch_nr' => $batchCount.$processDate,
                                'fk_attributeset_id' => 1
                            ]
                        );
                        if($article->wasRecentlyCreated) {$article->batch_nr=$batchCount.$processDate;$article->save();}
                        if($EchologAktiv){echo "\n".$row.": [A(".$article->id.")]";}
                    }else{if($EchologAktiv){echo "\n".$row.": [*A(".$article->id.")]";}}

                    //Article - Variations
                    $variation = Article_Variation::where('fk_article_id', '=', $article->id)
                    ->where('vstcl_identifier', '=', "vstcl-".$data[$EANNR])
                    ->where('fk_attributeset_id', '=', 1)->first();
                    // wenns die Variation schon gibt, keine Änderung, sonst UpdateCreate
                    if(!$variation)
                    {
                        $variation = Article_Variation::updateOrCreate(
                            [
                                'fk_article_id' => $article->id,
                                'vstcl_identifier' => "vstcl-".$data[$EANNR]
                            ],
                            [ 'fk_attributeset_id' => 1 ]
                        );
                        if($EchologAktiv){echo " "."[V(".$variation->id.")]";}
                    }else{if($EchologAktiv){echo " "."[*V(".$variation->id.")]";}}


                    if($variation->wasRecentlyCreated)
                    {   $variation->active = true; $variation->save();
                        $article->batch_nr=$batchCount.$processDate;$article->save();

                        $varColor = $data[$FARBNR];
                        if($varColor != '')
                        {   $varImgs = $article->variations()
                            ->where('id','!=', $variation->id)
                            ->whereHas('attributes', function($query) use($varColor){
                                $query->where('name','=','fee-color')->where('value','=', $varColor);
                            })->whereHas('images')->first();
                            if($varImgs) {
                                $varimgs = $varImgs->images()->get();
                                foreach($varimgs as $varimg) {
                                    $varimgAttrs = $varimg->attributes()->get();
                                    $newVarImg = Article_Variation_Image::updateOrCreate(
                                        [
                                            'fk_article_variation_id' => $variation->id,
                                            'location' => $varimg->location
                                        ],[ 'loaded' => 1 ]
                                    );
                                    if($EchologAktiv){echo " "."[IMG]";}
                                    foreach($varimgAttrs as $varimgAttr) {
                                        Article_Variation_Image_Attribute::updateOrCreate(
                                            ['fk_article_variation_image_id' => $newVarImg->id, 'name' => $varimgAttr->name],
                                            ['value' => $varimgAttr->value]
                                        );
                                    }
                                }
                            }
                        }
                    }


                    // beginn aktuelle Attribute sammeln
                    $articleAttributes = $article->attributes()->get(); $AttrChange=0;
                    $thisArticleAttributes = [];
                    foreach($articleAttributes as $articleAttribute)
                    { $thisArticleAttributes[$articleAttribute->name] = $articleAttribute->value; }
                    // ende aktuelle Attribute sammeln
                    if(isset($thisArticleAttributes['hersteller']) && $thisArticleAttributes['hersteller'] != $data[$LIEFERANT]
                    || !isset($thisArticleAttributes['hersteller'])){$article->updateOrCreateAttribute('hersteller', $data[$LIEFERANT]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['hersteller-nr']) && $thisArticleAttributes['hersteller-nr'] != $data[$LST_ID]
                    || !isset($thisArticleAttributes['hersteller-nr'])){$article->updateOrCreateAttribute('hersteller-nr', $data[$LST_ID]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['lieferartikel-bezeichnung']) && $thisArticleAttributes['lieferartikel-bezeichnung'] != $data[$LIEFARTIKELBEZEICHNUNG]
                    || !isset($thisArticleAttributes['lieferartikel-bezeichnung'])){$article->updateOrCreateAttribute('lieferartikel-bezeichnung', $data[$LIEFARTIKELBEZEICHNUNG]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['eigene-artikelnr']) && $thisArticleAttributes['eigene-artikelnr'] != $data[$EIGENEARTIKELNR]
                    || !isset($thisArticleAttributes['eigene-artikelnr'])){$article->updateOrCreateAttribute('eigene-artikelnr', $data[$EIGENEARTIKELNR]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-info1']) && $thisArticleAttributes['fee-info1'] != $data[$INFO1]
                    || !isset($thisArticleAttributes['fee-info1'])){$article->updateOrCreateAttribute('fee-info1', $data[$INFO1]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-info2']) && $thisArticleAttributes['fee-info2'] != $data[$INFO2]
                    || !isset($thisArticleAttributes['fee-info2'])){$article->updateOrCreateAttribute('fee-info2', $data[$INFO2]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-info3']) && $thisArticleAttributes['fee-info3'] != $data[$INFO3]
                    || !isset($thisArticleAttributes['fee-info3'])){$article->updateOrCreateAttribute('fee-info3', $data[$INFO3]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-info4']) && $thisArticleAttributes['fee-info4'] != $data[$INFO4]
                    || !isset($thisArticleAttributes['fee-info4'])){$article->updateOrCreateAttribute('fee-info4', $data[$INFO4]);$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-info5']) && $thisArticleAttributes['fee-info5'] != $data[$INFO5]
                    || !isset($thisArticleAttributes['fee-info5'])){$article->updateOrCreateAttribute('fee-info5', $data[$INFO5]);$AttrChange=1;}

                    // Saison und letzter Wareneingang speichern
                    if(isset($thisArticleAttributes['fee-sai_id']) && $thisArticleAttributes['fee-sai_id'] != $data[$SAI_ID].""
                    || !isset($thisArticleAttributes['fee-sai_id'])){$article->updateOrCreateAttribute('fee-sai_id', $data[$SAI_ID]."");$AttrChange=1;}
/*
                    if(isset($thisArticleAttributes['fee-sai_id']) && $thisArticleAttributes['fee-sai_id'] != $data[$SAI_ID].""
                    || !isset($thisArticleAttributes['fee-sai_id'])){$article->updateOrCreateAttribute('fee-sai_id', $data[$SAI_ID]."");$AttrChange=1;}
                    */
                    if(isset($thisArticleAttributes['fee-saison']) && $thisArticleAttributes['fee-saison'] != $data[$SAISON].""
                    || !isset($thisArticleAttributes['fee-saison'])){$article->updateOrCreateAttribute('fee-saison', $data[$SAISON]."");$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-saisonbezeichnung']) && $thisArticleAttributes['fee-saisonbezeichnung'] != $data[$SAISONBEZEICHNUNG].""
                    || !isset($thisArticleAttributes['fee-saisonbezeichnung'])){$article->updateOrCreateAttribute('fee-saisonbezeichnung', $data[$SAISONBEZEICHNUNG]."");$AttrChange=1;}

                    if(isset($thisArticleAttributes['fee-kollektion']) && $thisArticleAttributes['fee-kollektion'] != $data[$KOLLEKTION].""
                    || !isset($thisArticleAttributes['fee-kollektion'])){$article->updateOrCreateAttribute('fee-kollektion', $data[$KOLLEKTION]."");$AttrChange=1;}

                    if( (isset($data[$LETZTERWARENEINGANG]) && isset($thisArticleAttributes['fee-letzterwe']) && $thisArticleAttributes['fee-letzterwe'] != $data[$LETZTERWARENEINGANG]."" && $data[$LETZTERWARENEINGANG] != "")
                    || (isset($data[$LETZTERWARENEINGANG]) && !isset($thisArticleAttributes['fee-letzterwe']) ) )
                    {$article->updateOrCreateAttribute('fee-letzterwe', $data[$LETZTERWARENEINGANG]."");$AttrChange=1;}


                    if($AttrChange==1)
                    { if($EchologAktiv){echo " "."[A-Attr]";} }

                    // beginn aktuelle Preise sammeln
                    $variationPrices = $variation->prices()->get(); $PriceChange=0;
                    $thisVariationPrices = [];
                    foreach($variationPrices as $variationPrice)
                    { $thisVariationPrices[$variationPrice->name] = $variationPrice->value; }
                    // ende aktuelle Preise sammeln
                    $StandardPrice = $data[$VK];
                    $DiscountPrice = ($data[$VKWEB]!=""?$data[$VKWEB]:$data[$VK]);
                    if(isset($thisVariationPrices['standard']) && $thisVariationPrices['standard'] != $StandardPrice
                    || !isset($thisVariationPrices['standard']) )
                    {$variation->updateOrCreatePrice('standard', $StandardPrice);$PriceChange=1;}
                    if(isset($thisVariationPrices['discount']) && $thisVariationPrices['discount'] != $DiscountPrice
                    || !isset($thisVariationPrices['discount']) )
                    {$variation->updateOrCreatePrice('discount', $DiscountPrice);$PriceChange=1;}
                    if($PriceChange==1)
                    { if($EchologAktiv){echo " "."[P]";} }

                    $ThisColorAttrGroupId = ($data[$FARBNR] != "") ? ((isset($AttrSetFilterGroups['colors']))? $AttrSetFilterGroups['colors'] :  null ) : null;
                    $ThisSizeAttrGroupId = ($data[$GROESSE] != "") ? ((isset($AttrSetFilterGroups['sizes']))? $AttrSetFilterGroups['sizes'] :  null ) : null;
                    $ThisLengthAttrGroupId = ($data[$LAENGE] != "") ? ((isset($AttrSetFilterGroups['lengths']))? $AttrSetFilterGroups['lengths'] :  null ) : null;

                    $ThisFeeSize = $data[$GROESSE].(($data[$LAENGE] != "")? "/".$data[$LAENGE] : "");

                    // beginn aktuelle Attribute sammeln
                    $variationAttributes = $variation->attributes()->get(); $varAttrChange=0;
                    $thisVariationAttributes = [];
                    foreach($variationAttributes as $variationAttribute)
                    { $thisVariationAttributes[$variationAttribute->name] = $variationAttribute->value; }
                    // ende aktuelle Attribute sammeln
                    if(isset($thisVariationAttributes['fee-color']) && $thisVariationAttributes['fee-color'] != $data[$FARBNR]
                    || !isset($thisVariationAttributes['fee-color']) ){$variation->updateOrCreateAttribute('fee-color', $data[$FARBNR], $ThisColorAttrGroupId);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-form']) && $thisVariationAttributes['fee-form'] != $data[$LAENGE]
                    || !isset($thisVariationAttributes['fee-form'])){$variation->updateOrCreateAttribute('fee-form', $data[$LAENGE]);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-formCup']) && $thisVariationAttributes['fee-formCup'] != $data[$LAENGE]
                    || !isset($thisVariationAttributes['fee-formCup'])){$variation->updateOrCreateAttribute('fee-formCup', $data[$LAENGE]);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-formLaenge']) && $thisVariationAttributes['fee-formLaenge'] != $data[$LAENGE]
                    || !isset($thisVariationAttributes['fee-formLaenge'])){$variation->updateOrCreateAttribute('fee-formLaenge', $data[$LAENGE]);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-size']) && $thisVariationAttributes['fee-size'] != $ThisFeeSize
                    || !isset($thisVariationAttributes['fee-size'])){$variation->updateOrCreateAttribute('fee-size', $ThisFeeSize, $ThisSizeAttrGroupId);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-sizeLaenge']) && $thisVariationAttributes['fee-sizeLaenge'] != $data[$GROESSE]."/".$data[$LAENGE]
                    || !isset($thisVariationAttributes['fee-sizeLaenge'])){$variation->updateOrCreateAttribute('fee-sizeLaenge', $data[$GROESSE]."/".$data[$LAENGE]);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['fee-info1']) && $thisVariationAttributes['fee-info1'] != $data[$INFO1]
                    || !isset($thisVariationAttributes['fee-info1'])){$variation->updateOrCreateAttribute('fee-info1', $data[$INFO1]);$varAttrChange=1;}

                    if(isset($data[$LETZTERWARENEINGANG]) && isset($thisVariationAttributes['fee-letzterwe']) && $thisVariationAttributes['fee-letzterwe'] != $data[$LETZTERWARENEINGANG]
                    || (isset($data[$LETZTERWARENEINGANG]) &&!isset($thisVariationAttributes['fee-letzterwe'])) ){$variation->updateOrCreateAttribute('fee-letzterwe', $data[$LETZTERWARENEINGANG]);$varAttrChange=1;}

                    /// Größen Eigenschaften verarbeiten
                    if($data[$GROESSE] != "")
                    {   $data[$GROESSE] = strval($data[$GROESSE]);
                        if($article->has('categories')) {
                            // BEGIN Sammle Kategorien des Artikels, ihre Eigenschaften und deren Optionen
                            $Article_categories = $article->categories()->whereNull('fk_wawi_id')->get();
                            $CategoyEigenschaften = [];
                            foreach($Article_categories as $Article_category)
                            {   $Article_category_Eigenschaften = $Article_category->eigenschaften_cat()->get();
                                foreach($Article_category_Eigenschaften as $Article_category_Eigenschaft)
                                {   $Eigenschaft = Article_Eigenschaften::where('id','=',$Article_category_Eigenschaft->fk_eigenschaft_id)->first();
                                    if($Eigenschaft)
                                    {
                                        if(!in_array($Eigenschaft->name,$CategoyEigenschaften))
                                        {$CategoyEigenschaften[$Eigenschaft->name] = [];}

                                        $EigenschaftDatas = $Eigenschaft->eigenschaften()->get();
                                        foreach($EigenschaftDatas as $EigenschaftData)
                                        {   if(!in_array($EigenschaftData->value,$CategoyEigenschaften[$Eigenschaft->name]))
                                            {$CategoyEigenschaften[$Eigenschaft->name][] = $EigenschaftData->value;}
                                        }
                                    }

                                }
                            }
                            // ENDE Sammle Kategorien des Artikels, ihre Eigenschaften und deren Optionen

                            if(in_array($data[$GROESSE],$EUGroessenCodes)) // prüfe obs ein EU-Größen Code ist
                            {   // prüfe ob EU-Größen der Kategorien des Artikels zugewiesen sind ++ // prüfe ob Größe als Option bereits existiert
                                if(isset($CategoyEigenschaften["EU-Größen"]))
                                {
                                    if(!in_array($data[$GROESSE],$CategoyEigenschaften["EU-Größen"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['EU-Größen']->id,'name' => $GroessenEigenschaften['EU-Größen']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['EU-Größen']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    // Artikel der Eigenschaft Data zuweisen
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                            }else
                            {
                                if(isset($CategoyEigenschaften["Kragenweite"]))
                                {   if(!in_array($data[$GROESSE],$CategoyEigenschaften["Kragenweite"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['Kragenweite']->id,'name' => $GroessenEigenschaften['Kragenweite']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['Kragenweite']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                                if(isset($CategoyEigenschaften["Konfektion"]))
                                {   if(!in_array($data[$GROESSE],$CategoyEigenschaften["Konfektion"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['Konfektion']->id,'name' => $GroessenEigenschaften['Konfektion']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['Konfektion']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                                if(isset($CategoyEigenschaften["Jeansgröße"]))
                                {   if(!in_array($data[$GROESSE],$CategoyEigenschaften["Jeansgröße"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['Jeansgröße']->id,'name' => $GroessenEigenschaften['Jeansgröße']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['Jeansgröße']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                                if(isset($CategoyEigenschaften["Schuhgröße"]))
                                {   if(!in_array($data[$GROESSE],$CategoyEigenschaften["Schuhgröße"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['Schuhgröße']->id,'name' => $GroessenEigenschaften['Schuhgröße']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['Schuhgröße']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                                if(isset($CategoyEigenschaften["Gürtellänge"]))
                                {   if(!in_array($data[$GROESSE],$CategoyEigenschaften["Gürtellänge"]))
                                    {   $existData = Article_Eigenschaften_Data::updateOrCreate(['fk_eigenschaft_id' => $GroessenEigenschaften['Gürtellänge']->id,'name' => $GroessenEigenschaften['Gürtellänge']->name,'value' => $data[$GROESSE]]);  }
                                    else{   $existData = Article_Eigenschaften_Data::where('fk_eigenschaft_id', '=', $GroessenEigenschaften['Gürtellänge']->id)->where('value', '=', $data[$GROESSE])->first(); }
                                    Article_Eigenschaften_Articles::updateOrCreate(['fk_article_id' => $article->id,'fk_eigenschaft_data_id' => $existData->id,'active' => 1]);
                                }
                            }



                        } $AttrChange=1;
                    }



                    if($varAttrChange==1)
                    { if($EchologAktiv){echo " "."[V-Attr]";} }


                    if($AttrChange==1 || $PriceChange==1 || $varAttrChange==1)
                    { $article->batch_nr=$batchCount.$processDate;$article->save(); }

                    $stock = floor($data[$STUECK]);
                    //Stock
                    $variation->updateOrCreateStockInBranch($branch, $stock, $batchCount.$processDate);
                    if($EchologAktiv){echo " "."[Stock]";}


                    //22.01.2021 Tanju Özsoy - Wir verbinden nun alle kürzlich erstellten Artikel mit allen Providern - bei Update machen wir nichts
                    if($article->wasRecentlyCreated){
                        foreach($providers as $provider) {
                            ArticleProvider::updateOrCreate(
                                ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                                ['active' => 1]
                            );
                        }
                    }


                    //Set Article active with Stock
                    if($stock > 0) {
                        /*
                        if($article->wasRecentlyCreated) {
                            foreach($providers as $provider) {
                                ArticleProvider::updateOrCreate(
                                    ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                                    ['active' => 1]
                                );
                            }
                        }
                        */
                        if($variation->active == 0){$variation->active = 1; $variation->save();}
                        if($article->active == 0){$article->active = 1; $article->save();}
                    }else
                    {
                        $articleVariations = $article->variations()->get();
                        $GesamtStock = 0;
                        foreach($articleVariations as $articleVariation)
                        {   $varStock = $articleVariation->getStock();
                            $GesamtStock += $varStock;
                        }
                        if($variation->active == 1 && $variation->getStock()<=0){ $variation->active = 0;  $variation->save(); }

                        // Artikel deaktivieren wenn kein Stock
                        if($GesamtStock == 0 && $article->active == 1){ $article->active = 0; $article->save(); }
                    }
                    if($variation->active == 0 && $variation->getStock()>0){$variation->active = 1; $variation->save();$article->active = 1; $article->save();}




                    //Save processed Branch for Variation
                    if(!isset($processedEANs[$variation->id])) {
                        $processedEANs[$variation->id] = [];
                    }
                    $processedEANs[$variation->id][] = $branch->id;
                    if(!in_array($variation->id, $processedVariationIds)) {
                        $processedVariationIds[] = $variation->id;
                    }

                    $batchItemCount++;
                    if($batchItemCount >= 50) {
                        $batchItemCount = 0;
                        $batchCount++;
                    }

                    //Categories
                    $cat = Category::updateOrCreate(
                        [
                            'fk_wawi_id' => 1,'wawi_number' => $data[$WARENGRUPPE]
                        ],
                        [ 'wawi_name' => $data[$WARENGRUPPENBEZEICHNUNG] ]
                    );

                    foreach($cat->shopcategories()->get() as $shopcat) {
                        $article->categories()->syncWithoutDetaching($shopcat->id);
                    }
                    $article->categories()->syncWithoutDetaching($cat->id);
                    if($EchologAktiv){echo " "."[OK]";}
                }
                catch(Exception $ex) {
                    if($synchro && !$PartFile) {
                        $synchro->expected_count = $row;
                        $synchro->success_count = $row;
                        $synchro->fk_synchro_status_id = $errorSynchroS->id;
                        $synchro->end_date = date('Y-m-d H:i:s');
                        $synchro->filepath = $backupFilePath;
                        $synchro->save();
                    }

                    if($PartFile)
                    {
                        $synchro = Synchro::create(
                            [
                                'fk_synchro_type_id' => $synchroType->id,
                                'fk_synchro_status_id' => $errorSynchroS->id,
                                'end_date' => date('Y-m-d H:i:s'),
                                'filepath' => $backupFilePath
                            ]
                        );
                        $synchro->save();
                    }
                }

            }
            fclose($file_content);
            if(!in_array($customer, $secondWawi) && !$PartFile)
            {
                $notProcessed = Article_Variation::with(['branches'])
                ->whereHas('article', function($query) {
                    $wawi = WaWi::where('name', '=', 'FEE')->first();
                    $query->where('fk_wawi_id', '=', $wawi->id);
                })
                ->whereNotIn('id', $processedVariationIds)
                ->get();
                foreach($notProcessed as $notProcessedVar) {
                    $branches = $notProcessedVar->branches()->get();
                    foreach($branches as $branch) {
                        $branch->stock = 0;
                        $branch->save();
                    }
                }
                //Set unprocessed Stock to 0
                foreach($processedEANs as $processedEANKey => $processedEANVal) {
                    $variation = Article_Variation::find($processedEANKey);
                    $oldBranches = $variation->branches()->whereNotIn('fk_branch_id', $processedEANVal)->get();
                    foreach($oldBranches as $oldBranch) {
                        $oldBranch->stock = 0;
                        $oldBranch->save();
                    }
                }
            }

            // Check wenn Partfile, ob noch weitere PARTS übrig sind
            $RestPartsInFolder = 0;
            if($PartFile)
            {
                $ThisPartName = basename($ThisPartOrgName."_PART_","");

                $PARTfiles = Storage::disk('customers')->files($customer.'/'.$folderName);
                $PARTfiles = preg_grep("/^".$customer."\/feecsv\/$ThisPartName/", $PARTfiles);
                $RestPartsInFolder = ($PARTfiles)? count($PARTfiles) : 0 ;

                if($RestPartsInFolder == 0)
                {
                    $synchro = Synchro::where('filepath', '=',  $customer.'/feecsv_backup/articles-'.basename($ThisPartOrgName, ".csv").'.csv' )->first();
                    if($synchro) {
                        $synchro->fk_synchro_status_id = $successSynchroS->id;
                        $synchro->end_date = date('Y-m-d H:i:s');
                        $synchro->save();
                    }
                }
            }

            echo 'kurz vor dem Abschluss sind wir ' . $synchro . '  ' . $PartFile;

            if($synchro && !$PartFile) {
                $synchro->expected_count = $row;
                $synchro->success_count = $row;
                $synchro->fk_synchro_status_id = $successSynchroS->id;
                $synchro->end_date = date('Y-m-d H:i:s');
                $synchro->filepath = $customer.'/feecsv_backup/articles-'.basename($fileName, ".csv").'.csv';
                $synchro->save();
            }

            if($DeltaRepeat && $RestPartsInFolder == 0)
            {	if($EchologAktiv){echo "\n"."starte import:feedelta für ".$customer.' file: '.$DeltaRepeat;}
                $exitCode = Artisan::call('import:feedelta', [
                    'customer' => $customer, 'file' => $DeltaRepeat
                ]);
            }


            $VSShoptype = Provider_Type::where('provider_key','=','shop')->first();
            if($customer != "stilfaktor" && $VSShoptype && ((!$PartFile) || ($PartFile && $RestPartsInFolder == 0)) )
            { $ThisVSShops = Provider::where('fk_provider_type','=', $VSShoptype->id)->get();
                if($ThisVSShops) {
                    if($EchologAktiv){echo "\n"."export:articlebatches_kunde für ".$customer;}
                    $shopController = new VSShopController();
                    $shopController->article_batch($customer, $EchologAktiv);
                    //$exitBatch = Artisan::call('export:articlebatches_kunde', ['customer' => $customer, 'log' => true]);
                }
            }

            $ShowareShoptype = Provider_Type::where('provider_key','=','shopware')->first();
            if($ShowareShoptype && ((!$PartFile) || ($PartFile && $RestPartsInFolder == 0)) ) { $ThisVSShops = Provider::where('fk_provider_type','=', $ShowareShoptype->id)->get();
                if($ThisVSShops) {
                    if($EchologAktiv){echo "\n"."export:articlebatches_kunde für ".$customer;}
                    $shopController = new ShopwareAPIController();
                    $shopController->article_batch($customer, $EchologAktiv);
                }
            }


            if($PartFile && $RestPartsInFolder>1){$this->importFeeCsv($customer, $EchologAktiv);}

        }

    }

    //Check if File is currently being transferred
    private function checkTransferState($fileName){
        $size1 = Storage::disk('customers')->size($fileName);
        sleep(1);
        $size2 = Storage::disk('customers')->size($fileName);
        if ($size1 != $size2) {
            return false;
        }
        return true;
    }

}
