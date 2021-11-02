<?php

namespace App\Http\Controllers\Tenant\Providers\Zalando;

use App\Http\Controllers\Controller;

use App\Tenant\OrderEvents;
use App\Tenant\Article_Variation;
use App\Tenant\Branch;
use App\Tenant\Provider;
use App\Tenant\Order;
use App\Tenant\Order_Attribute;
use App\Tenant\OrderArticle;
use App\Tenant\Settings_Attribute;

use Illuminate\Http\Request;
use Response, Log;
use Storage, Config;

class ZalandoController extends Controller
{
    public function orderState(Request $request) {

        $feeExportAktiv = 1;

        $bodyContent = json_decode($request->getContent());

        //Wir schreiben mal die ganze Anfrage in die Logdatei von Zalando!!!
        Log::channel('zalando')->info("####################################################################################");
        Log::channel('zalando')->info("Zalando-Auftrag für " . config()->get('tenant.identifier'));
        Log::channel('zalando')->info('########');
        Log::channel('zalando')->info(serialize($bodyContent) );
        Log::channel('zalando')->info("####################################################################################");

        $event_id = (isset($bodyContent->event_id)) ? $bodyContent->event_id : null;
        $order_id = (isset($bodyContent->order_id)) ? $bodyContent->order_id : null;
        $order_number = (isset($bodyContent->order_number)) ? $bodyContent->order_number : null;
        $state = (isset($bodyContent->state)) ? $bodyContent->state : null;
        $store_id = (isset($bodyContent->store_id)) ? $bodyContent->store_id : null;
        if( (isset($bodyContent->store_id)) && $bodyContent->store_id == "0"){$store_id = "0";}
        $timestamp = (isset($bodyContent->timestamp)) ? $bodyContent->timestamp : null;
        $items = (isset($bodyContent->items)) ? $bodyContent->items : null;

        $branch=null;
        $branch = Branch::where('zalando_id', '=', $store_id)->first();
        /*
        if($store_id == '0')
        {   $branch = Branch::where('zalando_id', '=', $store_id)->first();

            if(!$branch)
            {
                $store_id='000';
                $branch = Branch::where('wawi_number', '=', '000')->first();
            }

        }
        else{$branch = Branch::where('zalando_id', '=', $store_id)->first();}
        */

        if(!$branch) {
            Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown store_id: '.$store_id);
            return response()->json(['error' => 'The sent store_id \"'.$store_id.'\" is unknown'], 400);
        }


        if(!$event_id || !$order_id || !$order_number || !$state || $store_id === false || !$timestamp || !$items) {
            Log::info(config()->get('tenant.identifier').': Zalando sent event with wrong format', $request->getContent() );
            return response()->json(['success' => true], 200);
        }

        //Check last event for order_number
        $lastOrderEvent = OrderEvents::where('order_number', '=', $order_number)->orderBy('created_at', 'desc')->first();
        $last_state = (($lastOrderEvent) ? $lastOrderEvent->state : null);

        //Save Event in DB
        $orderEvent = OrderEvents::create([
            'event_id' => $event_id,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'state' => $state,
            'last_state' => $last_state,
            'store_id' => $store_id,
            'timestamp' => $timestamp,
            'items' => serialize($items)
        ]);

        // ältere Einträge entfernen
        if($lastOrderEvent)
        { $oldOrderEvents = OrderEvents::where('order_number', '=', $order_number)->where('id', '!=', $orderEvent->id)->where('id', '!=', $lastOrderEvent->id)->delete(); }
        else { $oldOrderEvents = OrderEvents::where('order_number', '=', $order_number)->where('id', '!=', $orderEvent->id)->delete(); }


        // Update Order

        foreach($items as $item)
        {
            $item_id = $item->item_id;
            $ean = $item->ean;
            $price = $item->price;
            $currency = $item->currency;
            $article_number = $item->article_number;

            $variation = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$ean)->first();
            $variation_extra_ean = Article_Variation::where('extra_ean', '=', $ean)->first();
            if($variation_extra_ean){$variation = $variation_extra_ean;}
            if(!$variation) {
                Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown ean: '.$ean);
                continue;
            }
            if(!$variation->branches()->where('fk_branch_id', '=', $branch->id)->first()) {
                Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown branch: '.$branch->id.' for ean: '.$ean);
                continue;
            }
        }


        switch($state) {
            case 'assigned': $status = 1; $article_status=1; break;
            case 'routed': $status = 2; $article_status=2; break;
            case 'fulfilled': $status = 10; $article_status=3; break;
            case 'cancelled': $status = 5; $article_status=5; break;
            case 'returned': $status = 11; $article_status=5; break;
        }


        $orderExist = Order::where('za_number','=',$order_number)->first();
        if($orderExist)
        {
            $order = Order::updateOrCreate(
                ['za_number' => $order_number],
                [ 'fk_order_status_id' => $status ]);

            foreach($items as $item)
            {
                $item_id = $item->item_id;
                $ean = $item->ean;
                $price = $item->price;
                $currency = $item->currency;
                $article_number = $item->article_number;

                $variation = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$ean)->first();
                $variation_extra_ean = Article_Variation::where('extra_ean', '=', $ean)->first();
                if($variation_extra_ean){$variation = $variation_extra_ean;}
                if(!$variation) {continue;}
                if(!$variation->branches()->where('fk_branch_id', '=', $branch->id)->first()) {continue;}


                $orderArticle = OrderArticle::updateOrCreate(
                ['fk_article_id' => $variation->article()->first()->id,
                 'fk_article_variation_id' => $variation->id,
                 'fk_order_id' => $order->id ],
                [ 'fk_orderarticle_status_id' => $article_status,'price' => $price * 100]);

                //Hier prüfen mal, ob der orderarticle_status sich erst jetzt auf returned verändert hat!!!!!!!
                if($orderArticle->getOriginal('fk_orderarticle_status_id')!=$article_status){
                    $item->fk_orderarticle_status_id_changed=true;
                }
                else{
                    $item->fk_orderarticle_status_id_changed=false;
                }
            }
        }else
        {
            $orderCount = Order::count();
            $order = Order::create(
                [
                'fk_config_payment_id' => null,
                'fk_config_shipment_id' => null,
                'fk_order_status_id' => $status,
                'fk_provider_id' => Provider::where('fk_provider_type', '=', '2')->first()->id,
                'shipment_price' => 0,
                'voucher_price' => 0,
                'za_number' => $order_number,
                'number' => ++$orderCount
            ]);

            $orderAttr = Order_Attribute::updateOrCreate(
                [ 'fk_order_id' => $order->id,
                  'name' => 'za_store_id'],
                [ 'value' => $store_id ] );

            foreach($items as $item)
            {
                $item_id = $item->item_id;
                $ean = $item->ean;
                $price = $item->price;
                $currency = $item->currency;
                $article_number = $item->article_number;

                $variation = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$ean)->first();
                $variation_extra_ean = Article_Variation::where('extra_ean', '=', $ean)->first();
                if($variation_extra_ean){$variation = $variation_extra_ean;}
                if(!$variation) {continue;}
                if(!$variation->branches()->where('fk_branch_id', '=', $branch->id)->first()) {continue;}

                $orderArticle = OrderArticle::create([
                    'fk_article_id' => $variation->article()->first()->id,
                    'fk_article_variation_id' => $variation->id,
                    'fk_order_id' => $order->id,
                    'fk_orderarticle_status_id' => $article_status,
                    'quantity' => 1,
                    'tax' => \App\Helpers\VAT::getVAT(),
                    'price' => $price * 100
                ]);

                $item->fk_orderarticle_status_id_changed=true;

            }
        }


        // Ende Order update


        //START BUSINESSLOGIC
        $branch = Branch::where('zalando_id', '=', $store_id)->first();

        if(!$branch) {
            Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown store_id: '.$store_id);
            return response()->json(['success' => true], 200);
        }

        $VerkaufsFilialie = $branch->wawi_number; // Standard
        $FeeOnlineBranch = Settings_Attribute::where('name','=','fee_online_branch_id')->first();
        if($FeeOnlineBranch){$FeeOnlineBranch=$FeeOnlineBranch->value;
            $VerkaufsFilialie = $FeeOnlineBranch; // Wenn Online Filialien Nummer angegeben wurde
        }


        $fulfilledItems = [];

        foreach($items as $item)
        {   $item_id = $item->item_id;
            $ean = $item->ean;
            $price = $item->price;
            $currency = $item->currency;
            $article_number = $item->article_number;

            $variation = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$ean)->first();
            $variation_extra_ean = Article_Variation::where('extra_ean', '=', $ean)->first();
            $org_ean = $ean;
            if($variation_extra_ean)
            {   $variation = $variation_extra_ean;
                $org_ean = str_replace('vstcl-','',$variation_extra_ean->vstcl_identifier);
            }
            if(!$variation) {
                Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown ean: '.$ean);
                continue;
            }
            if(!$variation->branches()->where('fk_branch_id', '=', $branch->id)->first()) {
                Log::info(config()->get('tenant.identifier').': Zalando sent event with unknown branch: '.$branch->id.' for ean: '.$ean);
                continue;
            }

            $currentStock = $variation->getStockByBranchIds([$branch->id]);

            switch($state) {
                case 'assigned':
                    //set stock for item -1
                    if(!$orderExist){
                        $newStock = ($currentStock - 1);
                        $variation->updateOrCreateStockInBranch($branch,  (string)$newStock);

                        // Artikel für neue Bestellung in Fee Export Umsatzdatei aufnehmen
                        $UrsprungsFilialie = $branch->wawi_number;
                        if($feeExportAktiv == 1){$this->fee_export($variation, $order_number, 'V',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'zalando' );}
                    }
                break;
                case 'routed':
                    //set stock for old store_id +1 and new store_id -1
                    //Logic for last state
                    if($last_state) {
                        $last_store_id = $lastOrderEvent->store_id;
                        $last_branch = Branch::where('zalando_id', '=', $last_store_id)->first();
                        if($last_branch) {
                            $last_branch_stock = $variation->getStockByBranchIds([$last_branch->id]);

                            $variation->updateOrCreateStockInBranch($last_branch, (string)($last_branch_stock + 1));

                            // Artikel für neue Bestellung in Fee Export Umsatzdatei aufnehmen
                            $UrsprungsFilialie = $last_branch->wawi_number;
                            if($feeExportAktiv == 1){$this->fee_export($variation, $order_number, 'R',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'zalando' );}

                        }
                        else {
                            Log::info(config()->get('tenant.identifier').': Zalando sent routed event with unknown last store_id: '.$last_store_id.' for ean: '.$ean);
                        }
                    }

                    $newStock = ($currentStock - 1);
                    $variation->updateOrCreateStockInBranch($branch, (string)$newStock);

                    // Artikel für neue Bestellung in Fee Export Umsatzdatei aufnehmen
                    $UrsprungsFilialie = $branch->wawi_number;
                    if($feeExportAktiv == 1){$this->fee_export($variation, $order_number, 'V',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'zalando' );}

                break;
                case 'fulfilled':
                    //check item list with assigned item list and set cancelled items +1
                    $fulfilledItems[] = $item->item_id;
                break;
                case 'cancelled':
                    //set item stock +1
                    //Logic for last state
                    /*if(config()->get('tenant.identifier') != 'wunderschoen-mode')
                    { // 18.03.2021 - Bestandsveränderung für alle deaktiviert
                        $newStock = ($currentStock + 1);
                        $variation->updateOrCreateStockInBranch($branch, (string)$newStock);
                    } */
                    // Artikel für neue Bestellung in Fee Export Umsatzdatei aufnehmen
                    $UrsprungsFilialie = $branch->wawi_number;
                    if( ($feeExportAktiv == 1) &&  $item->fk_orderarticle_status_id_changed ){$this->fee_export($variation, $order_number, 'R',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'zalando' );}

                break;
                case 'returned':
                    //TODO: check if item can be used for selling again
                    //No Update ATM, it comes from WaWi
                    //set item stock +1
                    //Logic for last state
                    /*if(config()->get('tenant.identifier') != 'wunderschoen-mode')
                    {   // 18.03.2021 - Bestandsveränderung für alle deaktiviert
                        $newStock = ($currentStock + 1);
                        $variation->updateOrCreateStockInBranch($branch, (string)$newStock);
                    } */

                    // Artikel für neue Bestellung in Fee Export Umsatzdatei aufnehmen
                    $UrsprungsFilialie = $branch->wawi_number;
                    if( ($feeExportAktiv == 1) &&  $item->fk_orderarticle_status_id_changed==true )
                    {
                        $this->fee_export($variation, $order_number, 'R',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'zalando' );
                    }
                break;
                default:
                break;
            }

        }
        //Logic for last state
        //Update Stock for all cancelled items + 1 in the order
        if(!empty($fulfilledItems)) {
            if($lastOrderEvent){$last_items = \unserialize($lastOrderEvent->items);}
            else{$last_items = [];}

            foreach($last_items as $last_item) {
                $item_fulfilled = false;
                foreach($fulfilledItems as $fulfilledItemId) {
                    if($fulfilledItemId == $last_item->item_id) {
                        $item_fulfilled = true;
                    }
                }
                if(!$item_fulfilled) {
                    $cancelled_var = Article_Variation::where('vstcl_identifier', '=', 'vstcl-'.$last_item->ean)->first();
                    if($cancelled_var) {
                        if($cancelled_var->branches()->where('fk_branch_id', '=', $branch->id)->first()) {
                            $cancelled_stock = $cancelled_var->getStockByBranchIds([$branch->id]);

                            //if(config()->get('tenant.identifier') == "wunderschoen-mode") { $cancelled_var->updateOrCreateStockInBranch($branch, (string)($cancelled_stock));  } // 23.10.2020 geändert auf Wunsch von SS
                            //else { $cancelled_var->updateOrCreateStockInBranch($branch, (string)($cancelled_stock + 1)); }

                            $cancelled_var->updateOrCreateStockInBranch($branch, (string)($cancelled_stock)); // 26.10.2020 für alle aktivieren
                        }
                    }
                }
            }
        }

        //END BUSINESSLOGIC
        return response()->json(['success' => true], 200);
    }

    public function fee_export(Article_Variation $variation, $OrderNumber, $Art,$VerkaufsFilialie,$UrsprungsFilialie,$Ean, $Price, $Datum, $Zeit,$Typ=false,$manuell=false,$manuell_Tenant=false )
    {
        $tenant = config()->get('tenant.identifier');

        $aktiveTenants = [];
        switch($Typ)
        {   case "vsshop":
            $aktiveTenants = ["basic",
                "demo1",'obermann','pk-fashion','romeiks','melchior','vanhauth'
                /*,'mayer-burghausen'*/
                ,'velmo','4youjeans','hl','haider','mode-wittmann',"senft","frauenzimmer",
            ];
            break;
            case "zalando":
                $aktiveTenants = [ "basic",
                    "fischer-stegmaier","favors","pascha",'vanhauth','plager','fruehauf','mayer-burghausen',
                    'mehner','modebauer','neheim','scheibe','keller','schwoeppe',
                    'fashionobermann','obermann','bstone','sparwel','pk-fashion','romeiks'
                    ,'melchior','velmo','4youjeans','hl','haider','mode-wittmann',"senft",
                ];
            break;

        }

        if($manuell!=1 && in_array($tenant,$aktiveTenants) )
        {   $tenantFolders = Storage::disk('customers')->directories($tenant."/feecsv");
            $folderName = "export";
            if(in_array($tenant.'/feecsv/'.$folderName, $tenantFolders))
            {
                $files = Storage::disk('customers')->files($tenant."/feecsv/".$folderName);
                $filePath = storage_path()."/customers/".$tenant."/feecsv/".$folderName."/";
				usort($files, function($b, $a) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });

                if(empty($files)) {
                    $fileptr = fopen($filePath.$Datum."_".date("His")."_".str_replace("-", "_", $tenant).".orc","a+"); // open the file
                }else
                {
                    $fileName = $files[0];
                    $fileptr = fopen($filePath.basename($fileName),"a"); // open the file
                }
                // Backupdatei finden und ebenfalls beschreiben
                $BackupDateiname = 'Backup-FEE-export.orc';
                $BackupDateipfad = storage_path()."/customers/".$tenant.'/feecsv_backup/'.$BackupDateiname;
                if(Storage::disk('customers')->exists($tenant.'/feecsv_backup/'.$BackupDateiname))
                {$Backup_fileptr = fopen($BackupDateipfad,"a");}else{$Backup_fileptr = fopen($BackupDateipfad,"a+");}

                fputs(
                    $fileptr,
                     '"UMS",'
                    .$OrderNumber.','
                    .'"'.$Art.'",'
                    .$VerkaufsFilialie.','
                    .$UrsprungsFilialie.','
                    .'"'.$Ean.'",'
                    .'"'.$variation->getSizeText().'",'
                    .$Price.','
                    .'"'.$Datum.'",'
                    .'"'.$Zeit.'",'
                    .','."\n"
                );
                fclose($fileptr);
                // Backup clone data
                fputs(
                    $Backup_fileptr,
                     '"UMS",'
                    .$OrderNumber.','
                    .'"'.$Art.'",'
                    .$VerkaufsFilialie.','
                    .$UrsprungsFilialie.','
                    .'"'.$Ean.'",'
                    .'"'.$variation->getSizeText().'",'
                    .$Price.','
                    .'"'.$Datum.'",'
                    .'"'.$Zeit.'",'
                    .','."\n"
                ); fclose($Backup_fileptr);

                if(!empty($files)) { rename($filePath.basename($fileName), $filePath.$Datum."_".date("His")."_".str_replace("-", "_", $tenant).".orc");}

            }
        }
        if($manuell==1)
        {
            $tenant = ($manuell_Tenant)? $manuell_Tenant : "fischer-stegmaier";
            $tenantFolders = Storage::disk('customers')->directories($tenant."/feecsv");
            $folderName = "export";
            if(in_array($tenant.'/feecsv/'.$folderName, $tenantFolders))
            {
                $files = Storage::disk('customers')->files($tenant."/feecsv/".$folderName);
                $filePath = storage_path()."/customers/".$tenant."/feecsv/".$folderName."/";
				usort($files, function($b, $a) { return Storage::disk('customers')->lastModified($a) <=> Storage::disk('customers')->lastModified($b); });

                if(empty($files)) {
                    $fileptr = fopen($filePath.$Datum."_".date("His")."_".str_replace("-", "_", $tenant).".orc","a+"); // open the file
                }else
                {
                    $fileName = $files[0];
                    $fileptr = fopen($filePath.basename($fileName),"a"); // open the file
                }

                fputs(
                    $fileptr,
                     '"UMS",'
                    .$OrderNumber.','
                    .'"'.$Art.'",'
                    .$VerkaufsFilialie.','
                    .$UrsprungsFilialie.','
                    .'"'.$Ean.'",'
                    .'"'.$variation->getSizeText().'",'
                    .$Price.','
                    .'"'.$Datum.'",'
                    .'"'.$Zeit.'",'
                    .','."\n"
                );
                fclose($fileptr);
                if(!empty($files)) { rename($filePath.basename($fileName), $filePath.$Datum."_".date("His")."_".str_replace("-", "_", $tenant).".orc");}

            }
        }

    }
}
