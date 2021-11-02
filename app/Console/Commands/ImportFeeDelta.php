<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article, App\Tenant\Branch, App\Tenant\Article_Variation;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use Storage, Config;
use Log;

class ImportFeeDelta extends Command
{
    protected $signature = 'import:feedelta {customer} {file}';

    protected $description = 'Importiert die Delta-Datei die FEE für den Bestandsimport ausgibt.';

    public function __construct(){ parent::__construct();}

     public function handle()
     {
        $exec_for_customer = $this->argument('customer');
        if($exec_for_customer=="false"){$exec_for_customer=false;}
        $file_for_exec = $this->argument('file');
        if($file_for_exec=="false"){$file_for_exec=false;}

         //CSV Fields
         $STG_ID = 0; $FIL_ID = 1; $STUECK = 2; $EAN = 3;

         $customers = Storage::disk('customers')->directories();
         $tenants = Tenant::all();

         $deltaCustomers = [
            'melchior',
            'stilfaktor',
            'vanhauth',
            'demo2','demo3',
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
            'mayer-burghausen','cosydh', 'keller', 'haider', 'hl',
            'sparwel',
            'neheim',
            'mehner',
            'schwoeppe',
            'plager',
            'favors','fashionobermann','scheibe','modebauer', 'bstone', 'frauenzimmer','olgasmodewelt'
            ,'pk-fashion','romeiks','velmo','4youjeans'
         ];

         if($exec_for_customer && $exec_for_customer != "")
         {
            $deltaCustomers = [$exec_for_customer];
            Log::channel('single')->info("FeeCSV Delta Import EXEC start - ".$exec_for_customer);
            echo "FeeCSV Delta Import EXEC start - ".$exec_for_customer."\n";
         }

         foreach($customers as $customer) {
             if(!in_array($customer, $deltaCustomers)) {
                Log::channel('single')->info("FeeCSV Delta kein registirierter Kunde? - ".$customer);
                 continue; //Folder not mapped to registered delta file user
             }
             $customerFolders = Storage::disk('customers')->directories($customer);
             if(in_array($customer.'/feecsv', $customerFolders))
             {
                 if($file_for_exec)
                 {  $files = [];
                    $files[] = $file_for_exec;
                    echo "File: ".$file_for_exec."\n";
                    $OneFails = 0;
                    if(!Storage::disk('customers')->exists($file_for_exec)){
                        echo $file_for_exec . '__Test';
                        $OneFails = 1;
                    }
                    if($OneFails == 1){Log::channel('single')->info("FeeCSV Delta Import SKIP - ".$customer."(".$file_for_exec.")");continue;}
                 }
                 else
                 {
                    $files = Storage::disk('customers')->files($customer.'/feecsv');


                    $files = preg_grep('/^'.$customer.'\/feecsv\/Deltavisticleshop/', $files);

                    //Continue if no files by fee
                    if(empty($files)) {
                        Log::channel('single')->info("FeeCSV Delta keine Dateien - ".$customer);
                        $this->info( json_encode($files) );
                        $this->info("FeeCSV Delta keine Dateien - ".$customer);

                        continue;

                    }

                    //Sort files to process oldest first
                    usort($files, function($a, $b) {
                        return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b);
                    });

                    // Check Files exist
                    $OneFails = 0;
                    foreach($files as $fileName)
                    { if(!Storage::disk('customers')->exists($fileName)){$OneFails = 1;} }
                    if($OneFails == 1){Log::channel('single')->info("FeeCSV Delta Import SKIP - ".$customer."(".$fileName.")");continue;}

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

                 if(Synchro::where('fk_synchro_type_id','=','1')->latest())
                 { if(Synchro::where('fk_synchro_type_id','=','1')->latest()->first()->fk_synchro_status_id != "1")
                    {
                        $this->info("Für {$customer} wird der Vorgang wegen der Synchro ausgelassen!");
                        continue;
                    }
                }

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
                 if(!$synchroType || !$successSynchroS || !$errorSynchroS || !$inProgressSynchroS)
                 { $hasSynchro = false; }

                 $fileCount = 0;
                 foreach($files as $fileName) {
                    if(!$this->checkTransferState($fileName)) {

                        Log::channel('single')->info("FeeCSV Delta CheckTransferState meldet unstimmigkeit - ".$customer."(".$fileName.")");

                        continue; }

                    $backup_date = date('Y-m-d-H-i-s');
                    //Save Backup
                    if(!$file_for_exec)
                    {   if(!Storage::disk('customers')->exists($customer.'/feecsv_backup/delta-'.str_replace("delta-", "", basename($fileName, ".csv")).'.csv'))
                        { Storage::disk('customers')->copy($fileName, $customer.'/feecsv_backup/delta-'.str_replace("delta-", "", basename($fileName, ".csv")).'.csv'); }
                        else{Storage::disk('customers')->copy($fileName, $customer.'/feecsv_backup/delta-'.str_replace("delta-", "", basename($fileName, ".csv")).'_copy.csv');}
                    }


                    //Create temp file and write recent file content
                    $processTemp = tmpfile();
                    fwrite($processTemp, Storage::disk('customers')->get($fileName));

                    //Delete all files which are checked in this process
                    $thisUmsatzFile = strpos($fileName, ".orc");
                    if($thisUmsatzFile) { continue; }
                    if(!$file_for_exec)
                    {Storage::disk('customers')->delete($fileName);}

                    //Read File Content
                    $file_content = fopen(stream_get_meta_data($processTemp)['uri'], "r");
                    $row = 0;
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
                    Log::channel('single')->info("FeeCSV Delta Import - ".$customer." - ".$fileName);
                    while (($data = fgetcsv($file_content, 0, ";")) !== FALSE) {
                        $row++;
                        $num = count($data);
                        //Skip first Row
                        if($row == 1) { continue; }
                        try {
                           //Branch
                           $branch = Branch::where('wawi_ident','=','fee-'.$data[$FIL_ID])->first();
                           if(!$branch) { continue; }

                            //Article - Variations
                            $variation = Article_Variation::where([
                                ['vstcl_identifier','=','vstcl-'.str_replace('\'', '',$data[$EAN])]
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
                            $article = $variation->article()->first();
                            if($stock > 0) {
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
                            $success_count++;

                        }
                        catch(Exception $ex) {
                            if($synchro) {
                                $synchro->expected_count = $row - 1;
                                $synchro->success_count = $success_count;
                                $synchro->failed_count = $error_count;
                                $synchro->fk_synchro_status_id = $errorSynchroS->id;
                                $synchro->end_date = date('Y-m-d H:i:s');
                                $synchro->filepath = $customer.'/feecsv_backup/delta-'.str_replace("delta-", "", basename($fileName, ".csv")).'.csv';
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
                        $synchro->filepath = $customer.'/feecsv_backup/delta-'.str_replace("delta-", "", basename($fileName, ".csv")).'.csv';
                        $synchro->save();
                    }
                    $fileCount++;
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
