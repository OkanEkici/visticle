<?php
/*
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article, App\Tenant\Branch, App\Tenant\Article_Variation;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use Storage, Config;
use Log;

class ImportFeeStockCsv extends Command
{
    protected $signature = 'import:feestock';

    protected $description = 'Importiert den Bestand aus der FEE CSV Datei.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //CSV Fields
        $STA_ID = 0;
        $STG_ID = 1;
        $ARTIKELNR = 2;
        $FARBNR = 3;
        $LAENGE = 4;
        $LST_ID = 5;
        $GROESSE = 6;
        $FIL_ID = 7;
        $FILIALE = 8;
        $FIL_BESCHREIBUNG = 9;
        $SAI_ID = 10;
        $SAISON = 11;
        $WAR_ID = 12;
        $STUECK = 13;
        $VK = 14;
        $LIEFERANT = 15;
        $SAISONBEZEICHNUNG = 16;
        $WARENGRUPPE = 17;
        $WARENGRUPPENBEZEICHNUNG = 18;
        $LIEFARTIKELBEZEICHNUNG = 19;
        $EIGENEARTIKELNR = 20;
        $KOLLEKTION = 21;
        $INFO1 = 22;
        $INFO2 = 23;
        $INFO3 = 24;
        $INFO4 = 25;
        $INFO5 = 26;
        $BILD1 = 27;
        $BILD2 = 28;
        $BILD3 = 29;
        $BILD4 = 30;
        $BILD5 = 31;
        $EANNR = 32;
        $VKWEB = 33;
        $customers = Storage::disk('customers')->directories();

        $articleNameStaID = ['visc'];
        $articleNameColor = [];
        $tenants = Tenant::all();
        $tenantTeams = [];
        $deltaCustomers = [
            'melchior',
            'stilfaktor',
            'vanhauth',
            'demo3',
            'demo2', // Shopware Test
            'dhtextil',
            'fashionundtrends',
            'mode-wittmann',
            'wildhardt',
            'modemai',
            'wunderschoen-mode',
            'senft',
            'fischer-stegmaier',
            'mukila',
            'fruehauf'
         ];

        foreach ($tenants as $tenant) {
            $tenantTeams[] = $tenant->subdomain;
        }

        foreach($customers as $customer) {
            if(!in_array($customer, $tenantTeams)) {
                continue; //Folder not mapped to registered tenant
            }
            if(in_array($customer, $deltaCustomers)) {
                continue;
            }
            $customerFolders = Storage::disk('customers')->directories($customer);
            if(in_array($customer.'/feecsv', $customerFolders)) {
                $files = Storage::disk('customers')->files($customer.'/feecsv');
                //Continue if no files by fee
                if(empty($files)) {
                    continue;
                }

                //Sort files to process oldest first
                usort($files, function($a, $b) {
                    return Storage::disk('customers')->lastModified($b) <=> Storage::disk('customers')->lastModified($a);
                });

                //the most recent file to process
                $fileName = $files[0];

                if(!$this->checkTransferState($fileName)) {
                    continue;
                }
                
                $backup_date = date('Y-m-d-H-i-s');
                //Save Backup
                Storage::disk('customers')->copy($fileName, $customer.'/feecsv_backup/stock_'.basename($fileName, ".csv")."-".$backup_date.'.csv');

                //Create temp file and write recent file content
                $processTemp = tmpfile();
                fwrite($processTemp, Storage::disk('customers')->get($fileName));

                
                //Delete all files which are checked in this process
                Storage::disk('customers')->delete($fileName);
                //foreach ($files as $oldFile) { Storage::disk('customers')->delete($oldFile); }

                //Set DB Connection
                \DB::purge('tenant');
                $tenant = $tenants->where('subdomain','=', $customer)->first();

                $config = Config::get('database.connections.tenant');
                $config['database'] = $tenant->db;
                $config['username'] = $tenant->db_user;
                $config['password'] = decrypt($tenant->db_pw);
                config()->set('database.connections.tenant', $config);

                \DB::connection('tenant');
                
                //Read File Content
                $file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                $row = 0;
                $providers = Provider::all();
                $synchroType = Synchro_Type::where('key','=','fee_stock_update')->first();
                $successSynchroS = Synchro_Status::where('description','=','Erfolgreich')->first();
                $errorSynchroS = Synchro_Status::where('description','=','Fehlgeschlagen')->first();
                $inProgressSynchroS = Synchro_Status::where('description','=','In Bearbeitung')->first();
                $hasSynchro = true;
                $synchro = null;
                $processDate = date('YmdHis');
                $batchCount = 0;
                $batchItemCount = 0;
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

                Log::channel('single')->info("FeeCSV Stock Import - ".$customer." - ".$fileName);
                while (($data = fgetcsv($file_content, 0, ";")) !== FALSE) {
                    $row++;
                    $num = count($data);
                    if($num != 34 && $num != 35) {
                        echo "Strukturänderung";
                        break; //FEE changed Data Structure
                    }
                    //Skip first Row
                    if($row == 1) {
                        continue;
                    }

                    try {
                        //Branch
                        $branch = Branch::updateOrCreate(
                            [
                                'wawi_ident' => "fee-".$data[$FIL_ID]
                            ],
                            [
                            'name' => $data[$FIL_BESCHREIBUNG],
                            'active' => 1,
                            'wawi_number' => $data[$FILIALE]
                            ]
                        );

                        $article = Article::where([
                            ['vstcl_identifier','=','vstcl-'.$data[$ARTIKELNR]], 
                            ['fk_wawi_id','=',1]
                            ])->first();
                        
                        if(!$article) {
                            $error_count++;
                            continue;
                        }

                        //Article - Variations
                        $variation = Article_Variation::where([
                            ['fk_article_id','=',$article->id],
                            ['vstcl_identifier','=','vstcl-'.$data[$EANNR]]
                        ])->first();

                        if(!$variation) {
                            $error_count++;
                            continue;
                        }
                        $stock = floor($data[$STUECK]);
                        //Stock
                        $variation->updateOrCreateStockInBranch($branch, $stock, $batchCount.$processDate);
                        $batchItemCount++;
                        if($batchItemCount >= 50) {
                            $batchItemCount = 0;
                            $batchCount++;
                        }

                        //Set Article active with Stock
                        if($stock > 0) {
                            $article->active = 1;
                            $article->save();
                            foreach($providers as $provider) {
                                ArticleProvider::updateOrCreate(
                                    ['fk_provider_id' => $provider->id, 'fk_article_id' => $article->id, 'fk_article_variation_id' => null],
                                    ['active' => 1]
                                );
                            }
                        }
                        $success_count++;

                    }
                    catch(Exception $ex) {
                        if($synchro) {
                            $synchro->expected_count = $row - 1;
                            $synchro->success_count = $success_count;
                            $synchro->failed_count = $error_count;
                            $synchro->fk_synchro_status_id = $errorSynchroS->id;
                            $synchro->end_date = date('Y-m-d H:i:s');
                            $synchro->filepath = $customer.'/feecsv_backup/stock_'.basename($fileName, ".csv").'-'.$backup_date.'.csv';
                            $synchro->save();
                        }
                    }

                }
                fclose($file_content);
                if($synchro) {
                    $synchro->expected_count = $row - 1;
                    $synchro->success_count = $success_count;
                    $synchro->failed_count = $error_count;
                    $synchro->fk_synchro_status_id = $successSynchroS->id;
                    $synchro->end_date = date('Y-m-d H:i:s');
                    $synchro->filepath = $customer.'/feecsv_backup/stock_'.basename($fileName, ".csv").'-'.$backup_date.'.csv';
                    $synchro->save();
                }
            }
        }

    }

    //Check if File is currently being transferred
    private function checkTransferState($fileName) {
        $size1 = Storage::disk('customers')->size($fileName);
        sleep(1);
        $size2 = Storage::disk('customers')->size($fileName);
        if ($size1 != $size2) {
            return false;
        }
        return true;
    }
}
*/