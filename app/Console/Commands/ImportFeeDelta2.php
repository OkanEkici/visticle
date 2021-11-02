<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Article, App\Tenant\Branch, App\Tenant\Article_Variation;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use Storage, Config;
use Log;

class ImportFeeDelta2 extends Command
{
    protected $signature = 'import:feedelta2';
    protected $description = 'Importiert die Delta-Datei die FEE für den Bestandsimport ausgibt aus der 2. WaWi.';

    public function __construct()
    { parent::__construct(); }

     public function handle()
     {
         //CSV Fields
         $STG_ID = 0; $FIL_ID = 1; $STUECK = 2; $EAN = 3;
        
         $customers = Storage::disk('customers')->directories();
         $tenants = Tenant::all();

         $csv2customers = [ 'wunderschoen-mode' ];
 
         foreach($customers as $customer) {
             if(!in_array($customer, $csv2customers)) {
                 continue; //Folder not mapped to registered delta file user
             }
             $customerFolders = Storage::disk('customers')->directories($customer);
             if(in_array($customer.'/feecsv2', $customerFolders)) {
                 $files = Storage::disk('customers')->files($customer.'/feecsv2');

                 $files = preg_grep('/^'.$customer.'\/feecsv2\/Deltavisticleshop/', $files);
                 //Continue if no files by fee
                 if(empty($files)) { continue; }
                
                 //Sort files to process oldest first
                 usort($files, function($a, $b) {
                     return Storage::disk('customers')->lastModified($b) <=> Storage::disk('customers')->lastModified($a);
                 });
 
                 //the most recent file to process
                 $fileName = $files[0];
 
                 if(!$this->checkTransferState($fileName)) { continue; }
                 
                 $backup_date = date('Y-m-d-H-i-s');
                 //Save Backup
                 Storage::disk('customers')->copy($fileName, $customer.'/feecsv2_backup/delta-'.$backup_date.'.csv');
 
                 //Create temp file and write recent file content
                 $processTemp = tmpfile();
                 fwrite($processTemp, Storage::disk('customers')->get($fileName));
 
                 
                 //Delete all files which are checked in this process
                 foreach ($files as $oldFile) { Storage::disk('customers')->delete($oldFile); }
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
 
                 while (($data = fgetcsv($file_content, 0, ";")) !== FALSE) {
                     $row++;
                     $num = count($data);
                     //Skip first Row
                     if($row == 1) { continue; }
                     try 
                     {
                        $filId = 2000 + $data[$FIL_ID];

                        //Branch
                        $branch = Branch::where('wawi_ident','=','fee-'.$filId)->first();
                        if(!$branch) { continue; }
 
                         //Article - Variations
                         $variation = Article_Variation::where([
                             ['vstcl_identifier','=','vstcl-'.str_replace('\'', '',$data[$EAN])]
                         ])->first();
 
                         if(!$variation) { $error_count++; continue; }

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
                             $synchro->filepath = $customer.'/feecsv2_backup/delta-'.$backup_date.'.csv';
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
                     $synchro->filepath = $customer.'/feecsv2_backup/delta-'.$backup_date.'.csv';
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
