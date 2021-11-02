<?php

namespace App\Http\Controllers\Tenant\Providers\KLTrend;

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
use App\Helpers\VAT;
use App\Tenant\Article_Marketing;
use App\Tenant\Article_Variation_Price;
use App\Tenant\Brand;
use App\Tenant\BrandsSuppliers;

class KLTrendController extends Controller
{

    private $shops = [];

    public function __construct() {
        //parent::__construct();
    }

    public function importKLTrendJson($customer = false, $EchologAktiv = false)
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
        $ARTIKELNR = 'Artikelnummer';$SCHLUESSEL_ID='Schluessel_ID';
        $SHORT_DESCRIPTION='long_description';$LONG_DESCRIPTION='meta_description';
        $META_TITLE='meta_title';$META_KEYWORDS='meta_keywords';
        $STUERKENNZEICHEN='Mehrwertsteuerkennzeichen';
        $FARBNR = 'Farbkennzeichen_intern'; $FARBBEZEICHNUNG='Farb_Bezeichnung_intern';
        $LST_ID = 'Lieferant';$FIL_ID = 'FIL_Identnummer';$FILIALE = 'Identnummer';
        $FIL_BESCHREIBUNG = 'Filiale';;$SAISON = 'Saisonkennzeichen';$STUECK = 'Verfuegbare_Menge';$VK = 'Verkaufspreis';$HAUSPREIS='Hauspreis';
        $LIEFERANT = 'Lieferanten_Bezeichnung';$SAISONBEZEICHNUNG = 'Saison_Bezeichnung';$WARENGRUPPE = 'Warengruppe';$ARTIKELNAME='Artikelbezeichnung';$GTIN='GTIN';
        $WARENGRUPPENBEZEICHNUNG = 'Warengruppen_Bezeichnung'; $GEWICHT='weight';
        $EIGENEARTIKELNR = 'SERIENNR';$BRAND='Marke';
        $FEDAS='FEDAS';$FORM_MODELL='Form_Modell';$GROESSE='Grösse';$GROESSENPOSITION='GroessenPosition';
        $ZUSATZFELD='Zusatzfeld';$MATERIAL='Material';$MHD='mhd';$PRODUCTS_ORDER_DESCRIPTION='products_order_description';
        $DELIVERY_TIME='delivery_time';
        $VKWEB = 'Sonderpreis';
        $LETZTERWARENEINGANG = 35;
        $AKTIONSPREIS='AktionsPreis';
        $AKTIONVON='AktionVon';
        $AktionBIS='AktionBis';
        $Golf_Schaftflex='Golf_Schaftflex';
        $Gold_Haendigleit='Golf_Haendigleit';


        //Mehrwertsteuerkennzeichen
        $MEHRWERTSTUER=[
            1=>VAT::getVAT(VAT::ORDINARY),
            2=>VAT::getVAT(VAT::REDUCED),
            3=>0.00,
        ];


        $tenants = Tenant::all();
        $secondWawi = [ 'wunderschoen-mode' ];




        $folderName = 'kltrendjson';
        $customerFolders = Storage::disk('customers')->directories($customer);
        $import_file_name='export_identnummern.json';

        if(in_array($customer.'/'.$folderName, $customerFolders))
        {
            $files = Storage::disk('customers')->files($customer.'/'.$folderName);
            $files = preg_grep('/^'.$customer.'\/kltrendjson\/' . $import_file_name . '/', $files);



            //Continue if no files by fee
            if(empty($files)) { return; }

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
                $OrgFileDate = Storage::disk('customers')->lastModified($customer.'/kltrendjson_backup/articles-'.basename($ThisPartOrgName, ".json").'.json');

            }
            //check ob die aktuelle Datei älter ist als das letzte Bestandsupdate
            $DeltaRepeat = false;
            $Deltafiles = Storage::disk('customers')->files($customer.'/kltrendjson_backup/');
            $Deltafiles = preg_grep("/"."Deltavisticleshop"."/", $Deltafiles);
            usort($Deltafiles, function($b, $a) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });
            $currentFileDate = Storage::disk('customers')->lastModified($fileName);
            if(!$PartFile){$OrgFileDate = $currentFileDate;}
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
                if(!Storage::disk('customers')->exists($customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json").'.json'))
                {   try { Storage::disk('customers')->copy($fileName, $customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json").'.json'); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }
                    $backupFilePath = $customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json").'.json';
                }else
                {   try { Storage::disk('customers')->copy($fileName, $customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json")."_".$backup_date.'.json'); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }
                    $backupFilePath = $customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json")."_".$backup_date.'.json';
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
                        if(!Storage::disk('customers')->exists($customer.'/kltrendjson_backup/articles-'.basename($thisfileName, ".json").'.json'))
                        { try { Storage::disk('customers')->copy($thisfileName, $customer.'/kltrendjson_backup/articles-'.basename($thisfileName, ".json").'.json'); } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); } }
                        else
                        { try { Storage::disk('customers')->copy($thisfileName, $customer.'/kltrendjson_backup/articles-'.basename($thisfileName, ".json")."_".$backup_date.'.json');  } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); } }

                        try { Storage::disk('customers')->delete($thisfileName); } catch (FileNotFoundException $e) { Log::info($thisfileName." not found"); }
                    }
                }
                try { Storage::disk('customers')->delete($fileName); } catch (FileNotFoundException $e) { Log::info($fileName." not found"); }


                // Check File Size
                $maxSize = 10;//2 Mb
                $puffer = 0.4; $part = 1;
                $size = filesize ( stream_get_meta_data($processTemp)['uri'] ) / 1000000;
                if ($size  > $maxSize+$puffer)
                {

                    $fopen = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                    $counter=0;$firstline;
                    while (($line = fgets($fopen)) !== FALSE)
                    {   if($counter==0){$firstline = $line;}$counter++;
                        $path = $customer.'/kltrendjson/'.basename($fileName, ".json")."_PART_".$part."".'.json';
                        $ftowrite = (Storage::disk('customers')->exists($path))? fopen(Storage::disk('customers')->path('/').$path, "a") : fopen(Storage::disk('customers')->path('/').$path, "w");
                        fputs($ftowrite,$line);
                        clearstatcache();
                        $size = filesize ( Storage::disk('customers')->path('/').$path ) / 1000000;
                        if ($size  > $maxSize)
                        {	fclose($ftowrite);
                            $part++;
                            if($part>1)
                            {	$path = $customer.'/kltrendjson/'.basename($fileName, ".json")."_PART_".$part."".'.json';
                                $ftowrite = (Storage::disk('customers')->exists($path))? fopen(Storage::disk('customers')->path('/').$path, "a") : fopen(Storage::disk('customers')->path('/').$path, "w");
                                fputs($ftowrite,$firstline);
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
                    if($AttrGroup->name == "Gewicht"){ $AttrSetFilterGroups['weights'] = $AttrGroup->id; }
                }
            }

            // Prüfe ob Größen Eigenschaften bereits existieren
            /*
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
            */


            $providers = Provider::all();
            $synchroType = Synchro_Type::where('key','=','kltrend_import_json')->first();
            $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
            $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
            $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
            $hasSynchro = true; $synchro = null; $processDate = date('YmdHis');
            $batchCount = 0; $batchItemCount = 0;

            $processedVariationIds = [];
            $processedEANs = [];
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
                    if(!in_array($customer, $secondWawi))
                    {
                        $notProcessed = Article_Variation::with(['branches'])
                        ->whereHas('article', function($query) {
                            $wawi = WaWi::where('name', '=', 'KLTrend')->first();
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


            $content=file_get_contents(stream_get_meta_data($processTemp)['uri']);
            /*
                Wir haben nun den INhalt der gesamten JSON-Datei. Bei näherer Betrachtung ist aufgefallen,
                dass darin enthaltene Dezimalzahlen mit Kommatas dargestellt werden, statt mit Punkten.
                Bei JSon kann es da zu Problemen kommen, da Schlüsselwertpaare durch KOmmatas voneinander
                getrennt werden.
                Wir ersetzen nun alle Kommatas bei Dezimalwerten mit Punkten. Yeeeeeehaaaaaaa.
            */
            $search_pattern='/(\d+),(\d+)/';
            $replace_pattern='${1}.${2}';
            $content=preg_replace($search_pattern,$replace_pattern,$content);

            //Nun entfernen wir noch das BOM(Byte Order Mark) aus dem JSon, was zu Problemen führt mit PHP
            $content=trim($content, "\xEF\xBB\xBF");



            //Jetzt wandeln wir es in JSON um
            try{
                $content=json_decode($content,false,1000,JSON_INVALID_UTF8_IGNORE|JSON_PRESERVE_ZERO_FRACTION);
            }
            catch(\Exception $e){
                echo $e->getMessage();
            }


            $data_import=$content->daten_import;

            //Read File Content
            //$file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
            $row = 0;


            Log::channel('single')->info("KLTrendJson Import - ".$customer." - ".$fileName);
            if($EchologAktiv){echo "\n"."Beginne Log";}


            foreach($data_import as $data)
            {

                $row++;
                try {
                    //Branch
                    $branch = Branch::updateOrCreate(
                        [   'wawi_ident' => "kltrend-".$data->$FIL_BESCHREIBUNG ],
                        [   'name' => 'Lager ' .$data->$FIL_BESCHREIBUNG,
                            'active' => 1,
                            'wawi_number' => $data->$FIL_BESCHREIBUNG
                        ]
                    );


                    //Articles - Schluessel
                    $article_name = $data->$SCHLUESSEL_ID;


                    // wenns den Artikel schon gibt, keine Änderung, sonst UpdateCreate
                    $article = Article::updateOrCreate(
                        [   'vstcl_identifier' => "vstcl-".$article_name, 'fk_wawi_id' => 1 ],
                        [   'name' => $data->$ARTIKELNAME,
                            //'ean' => $data[$GTIN],
                            'sku'=>$article_name,
                            'number' => $data->$ARTIKELNR,
                            'batch_nr' => $batchCount.$processDate,
                            'short_description'=>$data->$SHORT_DESCRIPTION,
                            'description'=>$data->$LONG_DESCRIPTION,
                            'metatitle'=>$data->$META_TITLE,
                            'keywords'=>$data->$META_KEYWORDS,
                            'type'=>'article',
                            'tax'=>$MEHRWERTSTUER[$data->$STUERKENNZEICHEN],
                            'fk_attributeset_id' => 1
                        ]
                    );
                    if($article->wasRecentlyCreated) {$article->batch_nr=$batchCount.$processDate;$article->save();}
                    if($EchologAktiv){echo "\n".$row.": [A(".$article->id.")]";}

                    //Marke?
                    $brand=null;
                    if($data->$BRAND){
                        $brand_slug=$data->$BRAND;
                        $brand_slug = str_replace("ä", "ae", $brand_slug);
                        $brand_slug = str_replace("ü", "ue", $brand_slug);
                        $brand_slug = str_replace("ö", "oe", $brand_slug);
                        $brand_slug = str_replace("Ä", "Ae", $brand_slug);
                        $brand_slug = str_replace("Ü", "Ue", $brand_slug);
                        $brand_slug = str_replace("Ö", "Oe", $brand_slug);
                        $brand_slug = str_replace("ß", "ss", $brand_slug);
                        $brand_slug = str_replace("´", "", $brand_slug);

                        $brand=Brand::updateOrCreate(['name'=>$data->$BRAND],['slug'=>$brand_slug]);

                        //Gibt es schon eine Kombi aus Brand und Supplier?
                        $brand_supplier=BrandsSuppliers::updateOrCreate(['fk_brand_id'=>$brand->id,'hersteller-nr'=>$data->$LST_ID,'hersteller_name'=>$data->$LIEFERANT]);

                        //Brand dem Artikel zuweisen
                        $article->fk_brand_id=$brand->id;
                        $article->save();
                    }

                    //Article - Variations
                    $variation = Article_Variation::updateOrCreate(
                        [
                            'fk_article_id' => $article->id,
                            'vstcl_identifier' => "vstcl-".$data->$GTIN
                        ],
                        [
                            'fk_attributeset_id' => 1,
                            'ean' => $data->$GTIN,
                            'type'=>'article',
                        ]
                    );
                    if($EchologAktiv){echo " "."[V(".$variation->id.")]";}


                    if($variation->wasRecentlyCreated)
                    {   $variation->active = true; $variation->save();
                        $article->batch_nr=$batchCount.$processDate;$article->save();

                        $varColor = $data->$FARBNR;
                        if($varColor != '')
                        {   $varImgs = $article->variations()
                            ->where('id','!=', $variation->id)
                            ->whereHas('attributes', function($query) use($varColor){
                                $query->where('name','=','color-nr')->where('value','=', $varColor);
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
                    if(isset($thisArticleAttributes['hersteller']) && $thisArticleAttributes['hersteller'] != $data->$LIEFERANT
                    || !isset($thisArticleAttributes['hersteller'])){$article->updateOrCreateAttribute('hersteller', $data->$LIEFERANT);$AttrChange=1;}

                    if(isset($thisArticleAttributes['hersteller-nr']) && $thisArticleAttributes['hersteller-nr'] != $data->$LST_ID
                    || !isset($thisArticleAttributes['hersteller-nr'])){$article->updateOrCreateAttribute('hersteller-nr', $data->$LST_ID);$AttrChange=1;}


                    if(isset($thisArticleAttributes['seriennummer']) && $thisArticleAttributes['seriennummer'] != $data->$EIGENEARTIKELNR
                    || !isset($thisArticleAttributes['seriennummer'])){$article->updateOrCreateAttribute('seriennummer', $data->$EIGENEARTIKELNR);$AttrChange=1;}



                    // Saison und letzter Wareneingang speichern
                   /*
                    if(isset($thisArticleAttributes['fee-sai_id']) && $thisArticleAttributes['fee-sai_id'] != $data[$SAI_ID].""
                    || !isset($thisArticleAttributes['fee-sai_id'])){$article->updateOrCreateAttribute('fee-sai_id', $data[$SAI_ID]."");$AttrChange=1;}
                    */
                    if(isset($thisArticleAttributes['saison']) && $thisArticleAttributes['saison'] != $data->$SAISON.""
                    || !isset($thisArticleAttributes['saison'])){$article->updateOrCreateAttribute('saison', $data->$SAISON."");$AttrChange=1;}

                    if(isset($thisArticleAttributes['saisonbezeichnung']) && $thisArticleAttributes['saisonbezeichnung'] != $data->$SAISONBEZEICHNUNG.""
                    || !isset($thisArticleAttributes['saisonbezeichnung'])){$article->updateOrCreateAttribute('saisonbezeichnung', $data->$SAISONBEZEICHNUNG."");$AttrChange=1;}

                    //Gewicht
                    if(isset($thisArticleAttributes['gewicht']) && $thisArticleAttributes['gewicht'] != $data->$GEWICHT.""
                    || !isset($thisArticleAttributes['gewicht'])){$article->updateOrCreateAttribute('gewicht', $data->$GEWICHT."");$AttrChange=1;}

                    //FEDAS
                    if(isset($thisArticleAttributes['fedas']) && $thisArticleAttributes['fedas'] != $data->$FEDAS.""
                    || !isset($thisArticleAttributes['fedas'])){$article->updateOrCreateAttribute('fedas', $data->$FEDAS."");$AttrChange=1;}

                    //Form_Modell
                    if(isset($thisArticleAttributes['formModell']) && $thisArticleAttributes['formModell'] != $data->$FORM_MODELL.""
                    || !isset($thisArticleAttributes['formModell'])){$article->updateOrCreateAttribute('formModell', $data->$FORM_MODELL."");$AttrChange=1;}

                    //Material
                    if(isset($thisArticleAttributes['material']) && $thisArticleAttributes['material'] != $data->$MATERIAL.""
                    || !isset($thisArticleAttributes['material'])){$article->updateOrCreateAttribute('material', $data->$MATERIAL."");$AttrChange=1;}

                    //MHD
                    if(isset($thisArticleAttributes['mhd']) && $thisArticleAttributes['mhd'] != $data->$MHD.""
                    || !isset($thisArticleAttributes['mhd'])){$article->updateOrCreateAttribute('mhd', $data->$MHD."");$AttrChange=1;}

                    //Productsorderdescription
                    if(isset($thisArticleAttributes['productsOrderDescription']) && $thisArticleAttributes['productsOrderDescription'] != $data->$PRODUCTS_ORDER_DESCRIPTION.""
                    || !isset($thisArticleAttributes['productsOrderDescription'])){$article->updateOrCreateAttribute('productsOrderDescription', $data->$PRODUCTS_ORDER_DESCRIPTION."");$AttrChange=1;}

                     //Deliverytime
                     if(isset($thisArticleAttributes['deliveryTime']) && $thisArticleAttributes['deliveryTime'] != $data->$DELIVERY_TIME.""
                     || !isset($thisArticleAttributes['deliveryTime'])){$article->updateOrCreateAttribute('deliveryTime', $data->$DELIVERY_TIME."");$AttrChange=1;}

                     //GolfHaendigleit
                     if(isset($thisArticleAttributes['golfHaendigleit']) && $thisArticleAttributes['golfHaendigleit'] != $data->$Gold_Haendigleit.""
                     || !isset($thisArticleAttributes['golfHaendigleit'])){$article->updateOrCreateAttribute('golfHaendigleit', $data->$Gold_Haendigleit."");$AttrChange=1;}

                    //GolfSchaftflex
                    if(isset($thisArticleAttributes['golfSchaftflex']) && $thisArticleAttributes['golfSchaftflex'] != $data->$Golf_Schaftflex.""
                    || !isset($thisArticleAttributes['golfSchaftflex'])){$article->updateOrCreateAttribute('golfSchaftflex', $data->$Golf_Schaftflex."");$AttrChange=1;}
                     /*
                    if(isset($data[$LETZTERWARENEINGANG]) && isset($thisArticleAttributes['fee-letzterwe']) && $thisArticleAttributes['fee-letzterwe'] != $data[$LETZTERWARENEINGANG].""
                    || isset($data[$LETZTERWARENEINGANG]) && !isset($thisArticleAttributes['fee-letzterwe'])){$article->updateOrCreateAttribute('fee-letzterwe', $data[$LETZTERWARENEINGANG]."");$AttrChange=1;}
                    */


                    if($AttrChange==1)
                    { if($EchologAktiv){echo " "."[A-Attr]";} }

                    // beginn aktuelle Preise sammeln
                    $variationPrices = $variation->prices()->get(); $PriceChange=0;
                    $thisVariationPrices = [];
                    foreach($variationPrices as $variationPrice)
                    { $thisVariationPrices[$variationPrice->name] = $variationPrice->value; }
                    // ende aktuelle Preise sammeln
                    $StandardPrice = $data->$VK;
                    $DiscountPrice = ($data->$VKWEB!=""?$data->$VKWEB:$data->$VK);
                    $HousePrice=($data->$HAUSPREIS !=''?$data->$HAUSPREIS : null);
                    if(isset($thisVariationPrices['standard']) && $thisVariationPrices['standard'] != $StandardPrice
                    || !isset($thisVariationPrices['standard']) )
                    {$variation->updateOrCreatePrice('standard', $StandardPrice);$PriceChange=1;}
                    if(isset($thisVariationPrices['discount']) && $thisVariationPrices['discount'] != $DiscountPrice
                    || !isset($thisVariationPrices['discount']) )
                    {$variation->updateOrCreatePrice('discount', $DiscountPrice);$PriceChange=1;}
                    //Hauspreis
                    if(isset($thisVariationPrices['house']) && $thisVariationPrices['house'] != $HousePrice
                    || (!isset($thisVariationPrices['house']) && $HousePrice) )
                    {$variation->updateOrCreatePrice('house', $HousePrice);$PriceChange=1;}

                    //Aktionspreis
                    $promotion_price=$data->$AKTIONSPREIS;
                    $promotion_from=$data->$AKTIONVON;
                    $promotion_to=$data->$AktionBIS;
                    //Wenn der Preis auf Null steht, so löschen wir eine vorhandene Promotion
                    if($promotion_price==0){
                        //löschen
                        $variation->prices()->where('name','=','promotion')->delete();
                        //Wir löschen noch ein besthendes Marketing
                        Article_Marketing::query()->where('fk_article_id','=',$article->id)
                                        ->where('name','=','activate_promotion')->delete();
                    }
                    else{
                        //Variationspreis updaten oder erstellen
                        $keys=['fk_article_variation_id'=>$variation->id,'name'=>'promotion'];
                        $values=['value'=>number_format($promotion_price,2,',',''),'batch_nr' => $batchCount.$processDate];
                        $variation_price=Article_Variation_Price::updateOrCreate($keys,$values);

                        //Marketing updaten oder erstellen
                        $keys=['fk_article_id'=>$article->id,'name'=>'activate_promotion'];
                        $values=['from'=>$promotion_from,'until'=>$promotion_to];
                        $article_marketing=
                        Article_Marketing::updateOrCreate($keys,$values);
                    }

                    if($PriceChange==1)
                    { if($EchologAktiv){echo " "."[P]";} }

                    $ThisColorAttrGroupId = ($data->$FARBNR != "") ? ((isset($AttrSetFilterGroups['colors']))? $AttrSetFilterGroups['colors'] :  null ) : null;
                    $ThisSizeAttrGroupId = ($data->$GROESSE != "") ? ((isset($AttrSetFilterGroups['sizes']))? $AttrSetFilterGroups['sizes'] :  null ) : null;

                    // beginn aktuelle Attribute sammeln
                    $variationAttributes = $variation->attributes()->get(); $varAttrChange=0;
                    $thisVariationAttributes = [];
                    foreach($variationAttributes as $variationAttribute)
                    { $thisVariationAttributes[$variationAttribute->name] = $variationAttribute->value; }
                    // ende aktuelle Attribute sammeln
                    if(isset($thisVariationAttributes['color']) && $thisVariationAttributes['color'] != $data->$FARBNR
                    || !isset($thisVariationAttributes['color']) ){$variation->updateOrCreateAttribute('color', $data->$FARBNR, $ThisColorAttrGroupId);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['colorName']) && $thisVariationAttributes['colorName'] != $data->$FARBBEZEICHNUNG
                    || !isset($thisVariationAttributes['colorName']) ){$variation->updateOrCreateAttribute('colorName', $data->$FARBBEZEICHNUNG, $ThisColorAttrGroupId);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['size']) && $thisVariationAttributes['size'] != $data->$GROESSE
                    || !isset($thisVariationAttributes['size'])){$variation->updateOrCreateAttribute('size', $data->$GROESSE,$ThisSizeAttrGroupId);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['sizePosition']) && $thisVariationAttributes['sizePosition'] != $data->$GROESSENPOSITION
                    || !isset($thisVariationAttributes['sizePosition'])){$variation->updateOrCreateAttribute('sizePosition', $data->$GROESSENPOSITION);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['extraField']) && $thisVariationAttributes['extraField'] != $data->$ZUSATZFELD
                    || !isset($thisVariationAttributes['extraField'])){$variation->updateOrCreateAttribute('extraField', $data->$ZUSATZFELD);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['filIdentnummer']) && $thisVariationAttributes['filIdentnummer'] != $data->$FIL_ID
                    || !isset($thisVariationAttributes['filIdentnummer'])){$variation->updateOrCreateAttribute('filIdentnummer', $data->$FIL_ID);$varAttrChange=1;}

                    if(isset($thisVariationAttributes['identnummer']) && $thisVariationAttributes['identnummer'] != $data->$FILIALE
                    || !isset($thisVariationAttributes['identnummer'])){$variation->updateOrCreateAttribute('identnummer', $data->$FILIALE);$varAttrChange=1;}



                    /// Größen Eigenschaften verarbeiten
                    /*
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
                    */



                    if($varAttrChange==1)
                    { if($EchologAktiv){echo " "."[V-Attr]";} }


                    if($AttrChange==1 || $PriceChange==1 || $varAttrChange==1)
                    { $article->batch_nr=$batchCount.$processDate;$article->save(); }

                    $stock = floor($data->$STUECK);
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
                            'fk_wawi_id' => 1,'wawi_number' => $data->$WARENGRUPPE
                        ],
                        [ 'wawi_name' => $data->$WARENGRUPPENBEZEICHNUNG ]
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

            if(!in_array($customer, $secondWawi) && !$PartFile)
            {
                $notProcessed = Article_Variation::with(['branches'])
                ->whereHas('article', function($query) {
                    $wawi = WaWi::where('name', '=', 'KLTrend')->first();
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
                $PARTfiles = preg_grep("/^".$customer."\/kltrendjson\/$ThisPartName/", $PARTfiles);
                $RestPartsInFolder = ($PARTfiles)? count($PARTfiles) : 0 ;

                if($RestPartsInFolder == 0)
                {
                    $synchro = Synchro::where('filepath', '=',  $customer.'/kltrendjson_backup/articles-'.basename($ThisPartOrgName, ".json").'.json' )->first();
                    if($synchro) {
                        $synchro->fk_synchro_status_id = $successSynchroS->id;
                        $synchro->end_date = date('Y-m-d H:i:s');
                        $synchro->save();
                    }
                }
            }

            if($synchro && !$PartFile) {
                $synchro->expected_count = $row;
                $synchro->success_count = $row;
                $synchro->fk_synchro_status_id = $successSynchroS->id;
                $synchro->end_date = date('Y-m-d H:i:s');
                $synchro->filepath = $customer.'/kltrendjson_backup/articles-'.basename($fileName, ".json").'.json';
                $synchro->save();
            }

            /*
            if($DeltaRepeat && $RestPartsInFolder == 0)
            {	if($EchologAktiv){echo "\n"."starte import:feedelta für ".$customer.' file: '.$DeltaRepeat;}
                $exitCode = Artisan::call('import:feedelta', [
                    'customer' => $customer, 'file' => $DeltaRepeat
                ]);
            }
            */


            //########  Export für VSShops  #############

            /*
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
            */

            //########  Export für Shopware  #############
            /*
            $ShowareShoptype = Provider_Type::where('provider_key','=','shopware')->first();
            if($ShowareShoptype && ((!$PartFile) || ($PartFile && $RestPartsInFolder == 0)) ) { $ThisVSShops = Provider::where('fk_provider_type','=', $ShowareShoptype->id)->get();
                if($ThisVSShops) {
                    if($EchologAktiv){echo "\n"."export:articlebatches_kunde für ".$customer;}
                    $shopController = new ShopwareAPIController();
                    $shopController->article_batch($customer, $EchologAktiv);
                }
            }
            */


            if($PartFile && $RestPartsInFolder>1){$this->importKLTrendJson($customer, $EchologAktiv);}
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
