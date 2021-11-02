<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant, App\Tenant\Branch, App\Tenant\Article, App\Tenant\Article_Variation, App\Tenant\Category;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\WaWi;
use App\Tenant\Attribute_Set;
use Storage, Config;
use Illuminate\Support\Facades\Artisan;
use Log;

class ImportFeeCsv2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:feecsv2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importiert Artikel die von FEE per FTP im 2. Ordner abgelegt werden.';

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
        //CSV Fields
        $STA_ID = 0; $STG_ID = 1; $ARTIKELNR = 2; $FARBNR = 3; $LAENGE = 4;
        $LST_ID = 5; $GROESSE = 6; $FIL_ID = 7; $FILIALE = 8; $FIL_BESCHREIBUNG = 9;
        $SAI_ID = 10; $SAISON = 11; $WAR_ID = 12; $STUECK = 13; $VK = 14;
        $LIEFERANT = 15; $SAISONBEZEICHNUNG = 16; $WARENGRUPPE = 17; $WARENGRUPPENBEZEICHNUNG = 18;
        $LIEFARTIKELBEZEICHNUNG = 19; $EIGENEARTIKELNR = 20; $KOLLEKTION = 21;
        $INFO1 = 22; $INFO2 = 23; $INFO3 = 24; $INFO4 = 25; $INFO5 = 26;
        $BILD1 = 27; $BILD2 = 28; $BILD3 = 29; $BILD4 = 30; $BILD5 = 31;
        $EANNR = 32; $VKWEB = 33;
        $customers = Storage::disk('customers')->directories();

        $articleNameStaID = ['visc'];
        $articleNameColor = [];
        $tenants = Tenant::all();
        $tenantTeams = [];

        //Filial IDs wie sie eigentlich sein sollten
        $tenantBranchConfigs = [
            'wunderschoen-mode' => [
                '000' => '050',
                '004' => '051',
                '014' => '052',
                '015' => '053',
                '011' => '070',
                '013' => '071',
                '008' => '018'
            ]
        ];

        $csv2customers = [ 'wunderschoen-mode' ];
        $secondWawi = [ 'wunderschoen-mode' ];

        foreach ($tenants as $tenant) {
            $tenantTeams[] = $tenant->subdomain;
        }

        foreach($customers as $customer) {
            $isDelta = false;
            if(!in_array($customer, $tenantTeams)) { continue;  }//Folder not mapped to registered tenant
            if(!in_array($customer, $csv2customers)) { continue; }
            $isDelta = true;
            $folderName = 'feecsv2';

            $customerFolders = Storage::disk('customers')->directories($customer);
            if(in_array($customer.'/'.$folderName, $customerFolders)) {
                $files = Storage::disk('customers')->files($customer.'/'.$folderName);

                if($isDelta) { $files = preg_grep('/^'.$customer.'\/feecsv2\/visticleshop/', $files); }

                //Continue if no files by fee
                if(empty($files)) { continue; }

                //Sort files to process oldest first
                usort($files, function($a, $b) {
                    return Storage::disk('customers')->lastModified($b) <=> Storage::disk('customers')->lastModified($a);
                });

                //the most recent file to process
                $fileName = $files[0];

                if(!$this->checkTransferState($fileName)) { continue; }

                if($isDelta) {
                    $backup_date = date('Y-m-d-H-i-s');
                    //Save Backup
                    Storage::disk('customers')->copy($fileName, $customer.'/feecsv2_backup/articles-'.basename($fileName, ".csv").'.csv');
                }

                //Create temp file and write recent file content
                $processTemp = tmpfile();
                fwrite($processTemp, Storage::disk('customers')->get($fileName));

                if($isDelta) {
                    //Delete all files which are checked in this process
                    Storage::disk('customers')->delete($fileName);
                    //foreach ($files as $oldFile) { Storage::disk('customers')->delete($oldFile); }
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

                $AttrSetGroups = [];
                $AttrSetGroups = Attribute_Set::where('id', '=', '1')->with('groups')->get();
                if(isset($AttrSetGroups[0])){$AttrSetGroups = $AttrSetGroups[0]->groups;}
                // Filter Gruppen aussondern
                $AttrSetFilterGroups = [];
                foreach($AttrSetGroups as $AttrGroup)
                {   if($AttrGroup->is_filterable == 1)
                    {
                        if($AttrGroup->name == "Farbe"){ $AttrSetFilterGroups['colors'] = $AttrGroup->id; }
                        if($AttrGroup->name == "Größe"){ $AttrSetFilterGroups['sizes'] = $AttrGroup->id; }
                        if($AttrGroup->name == "Länge"){ $AttrSetFilterGroups['lengths'] = $AttrGroup->id; }
                    }
                }

                //Read File Content
                $file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                $row = 0;
                $providers = Provider::all();
                $synchroType = Synchro_Type::where('key','=','fee_import_csv')->first();
                $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
                $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
                $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
                $hasSynchro = true;
                $synchro = null;
                $processDate = date('YmdHis');
                $batchCount = 0;
                $batchItemCount = 0;
                $processedVariationIds = [];
                $processedEANs = [];
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
                Log::channel('single')->info("FeeCSV2 Import - ".$customer." - ".$fileName);
                while (($data = fgetcsv($file_content, 0, ";")) !== FALSE)
                {
                    $row++;
                    //Skip first Row
                    if($row == 1) { continue; }

                    //Process data
                    try {
                        if(!isset($tenantBranchConfigs[$customer][$data[$FILIALE]]))
                        { continue; }

                        $filId = 2000 + $data[$FIL_ID];
                        $filiale = $tenantBranchConfigs[$customer][$data[$FILIALE]];

                        //Branch
                        $branch = Branch::updateOrCreate(
                            [ 'wawi_ident' => "fee-".$filId ]
                            ,[
                            'name' => $data[$FIL_BESCHREIBUNG],
                            'active' => 1,
                            'wawi_number' => $filiale
                            ]
                        );

                        //Articles
                        $article_name = $data[$ARTIKELNR];
                        if(in_array($customer, $articleNameStaID)) { $article_name .= "_".$data[$STA_ID]; }
                        if(in_array($customer, $articleNameColor)) { $article_name .= '--'.$data[$FARBNR]; }

                        $article = Article::updateOrCreate(
                            [
                                'vstcl_identifier' => "vstcl-".$article_name,
                                'fk_wawi_id' => 1
                            ],
                            [
                                'name' => $data[$EIGENEARTIKELNR],
                                'ean' => $data[$EANNR],
                                'number' => $data[$ARTIKELNR],
                                'batch_nr' => $batchCount.$processDate,
                                'fk_attributeset_id' => 1
                            ]
                        );

                        //22.01.2021 Tanju Özsoy - Wir verbinden nun alle kürzlich erstellten Artikel mit allen Providern - bei Update machen wir nichts
                        if($article->wasRecentlyCreated){
                            foreach($providers as $provider) {
                                ArticleProvider::updateOrCreate(
                                    ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                                    ['active' => 1]
                                );
                            }
                        }

                        //Article - Variations
                        $variation = Article_Variation::updateOrCreate(
                            [
                                'fk_article_id' => $article->id,
                                'vstcl_identifier' => "vstcl-".$data[$EANNR]
                            ],
                            [ 'fk_attributeset_id' => 1 ]
                        );
                        if($variation->wasRecentlyCreated) {
                            $variation->active = true;
                            $variation->save();
                        }

                        $article->updateOrCreateAttribute('hersteller', $data[$LIEFERANT]);
                        $article->updateOrCreateAttribute('hersteller-nr', $data[$LST_ID]);
                        $article->updateOrCreateAttribute('lieferartikel-bezeichnung', $data[$LIEFARTIKELBEZEICHNUNG]);
                        $article->updateOrCreateAttribute('eigene-artikelnr', $data[$EIGENEARTIKELNR]);
                        $article->updateOrCreateAttribute('fee-info1', $data[$INFO1]);
                        $article->updateOrCreateAttribute('fee-info2', $data[$INFO2]);
                        $article->updateOrCreateAttribute('fee-info3', $data[$INFO3]);
                        $article->updateOrCreateAttribute('fee-info4', $data[$INFO4]);
                        $article->updateOrCreateAttribute('fee-info5', $data[$INFO5]);


                        $variation->updateOrCreatePrice('standard', ($data[$VKWEB]!=""?$data[$VKWEB]:$data[$VK]));
                        $variation->updateOrCreatePrice('discount', ($data[$VKWEB]!=""?$data[$VKWEB]:$data[$VK]));

                        $ThisColorAttrGroupId = ($data[$FARBNR] != "") ? ((isset($AttrSetFilterGroups['colors']))? $AttrSetFilterGroups['colors'] :  null ) : null;
                        $ThisSizeAttrGroupId = ($data[$GROESSE] != "") ? ((isset($AttrSetFilterGroups['sizes']))? $AttrSetFilterGroups['sizes'] :  null ) : null;
                        $ThisLengthAttrGroupId = ($data[$LAENGE] != "") ? ((isset($AttrSetFilterGroups['lengths']))? $AttrSetFilterGroups['lengths'] :  null ) : null;

                        $ThisFeeSize = $data[$GROESSE].(($data[$LAENGE] != "")? "/".$data[$LAENGE] : "");

                        $variation->updateOrCreateAttribute('fee-color', $data[$FARBNR], $ThisColorAttrGroupId);
                        $variation->updateOrCreateAttribute('fee-form', $data[$LAENGE]);
                        $variation->updateOrCreateAttribute('fee-formCup', $data[$LAENGE]);
                        $variation->updateOrCreateAttribute('fee-formLaenge', $data[$LAENGE]);
                        $variation->updateOrCreateAttribute('fee-size', $ThisFeeSize, $ThisSizeAttrGroupId);
                        $variation->updateOrCreateAttribute('fee-sizeLaenge', $data[$GROESSE]."/".$data[$LAENGE]);
                        $variation->updateOrCreateAttribute('fee-info1', $data[$INFO1]);

                        if($isDelta) {
                            $stock = floor($data[$STUECK]);
                            //Stock
                            $variation->updateOrCreateStockInBranch($branch, $stock, $batchCount.$processDate);


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

                        }

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
                                'fk_wawi_id' => 1,
                                'wawi_number' => $data[$WARENGRUPPE]
                            ],
                            [
                                'wawi_name' => $data[$WARENGRUPPENBEZEICHNUNG]
                            ]
                        );

                        foreach($cat->shopcategories()->get() as $shopcat) {
                            $article->categories()->syncWithoutDetaching($shopcat->id);
                        }
                        $article->categories()->syncWithoutDetaching($cat->id);

                    }
                    catch(Exception $ex) {
                        if($synchro) {
                            $synchro->expected_count = $row;
                            $synchro->success_count = $row;
                            $synchro->fk_synchro_status_id = $errorSynchroS->id;
                            $synchro->end_date = date('Y-m-d H:i:s');
                            $synchro->filepath = ($isDelta) ? $customer.'/feecsv2_backup/articles-'.basename($fileName, ".csv").'.csv' : $fileName;
                            $synchro->save();
                        }
                    }

                }
                fclose($file_content);
                if(!in_array($customer, $secondWawi)) {
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



                if($synchro) {
                    $synchro->expected_count = $row;
                    $synchro->success_count = $row;
                    $synchro->fk_synchro_status_id = $successSynchroS->id;
                    $synchro->end_date = date('Y-m-d H:i:s');
                    $synchro->filepath = ($isDelta) ? $customer.'/feecsv2_backup/articles-'.basename($fileName, ".csv").'.csv' : $fileName;
                    $synchro->save();
                }
                $exitBatch = Artisan::call('export:articlebatches_kunde', ['customer' => $customer,'log' => false]);
            }
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
