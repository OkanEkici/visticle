<?php

namespace App\Console\Commands\Check24;

use App\Helpers\Miscellaneous;
use App\Manager\Plattform\PlattformManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use App\Tenant, App\Tenant\Branch;
use App\Tenant\Article_Variation;
use App\Tenant\Article_Variation_Attribute;
use App\Tenant\Article_Attribute;
use App\Tenant\Category;
use App\Tenant\OrderArticle;
use App\Tenant\Config_Shipment;
use App\Tenant\Order_Status;
use App\Tenant\Provider, App\Tenant\ArticleProvider;
use App\Tenant\Order;
use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use App\Tenant\Invoice;
use App\Tenant\Invoice_Status;
use App\Tenant\Order_Attribute;
use App\Http\Controllers\Tenant\Providers\Zalando\ZalandoController;
use Illuminate\Support\Facades\DB;
use App\Tenant\WaWi;
use Storage, Config;
use Log;
use Illuminate\Support\Facades\Artisan;
use League\Csv\Writer;
use SimpleXMLElement;
use stdClass;

class Check24ImportOrder extends Command
{
    protected $signature = 'import:check24_import_order {customer}';
    protected $description = 'Importiert zu einem bestimmten Kunden die Auftragsdatei';


    public function __construct()
    {
        parent::__construct();
    }

    protected $customer;
    protected $provider=null;

    protected function loadProvider(){
        $this->provider=Provider::query()->whereHas('type',function($query){
            $query->where('provider_key','check24');
        })->first();
    }
    public function handle()
    {
        $exec_for_customer = $this->argument('customer');

        if($exec_for_customer=="false"){$exec_for_customer=false;}
        if(!$exec_for_customer){return;}

        $customer=$exec_for_customer;
        $this->customer=$exec_for_customer;
        //Tenant abgreifen nach dem Customer, also subdomain
        $tenant=Tenant::where('subdomain','=',$customer)->first();

        //Set DB Connection
        Miscellaneous::loadTenantDBConnection($customer);


        //Provider laden
        $this->loadProvider();

        //jetzt import-Vorgang starten
        $count=$this->importCheck24Orders();

        //jetzt führen wir die Verarbeitung unserer importierten Orderdateien durch
        $this->executeCheck24Orders();
    }
    protected function executeCheck24Orders(){
        $internal_import_folder_name="{$this->customer}/".config('plattform-manager.check24.internal_import_folder_order');
        //Wenn nicht, so erstellen wir den Ordner
        Storage::disk('customers')->makeDirectory("{$internal_import_folder_name}");

        //Jetz holen wir die Dateiliste
        $file_list=Storage::disk('customers')->files($internal_import_folder_name);

        foreach($file_list as $file){
            $file_path=Storage::disk('customers')->path($file);
            $order_xml=simplexml_load_file($file_path);

            //UM es einfach zu machen schliessen wir alles jetzt in eine Transaktion.
            //Sollte irgend etwas schief laufen, so können wir alles rückgängig machen!
            DB::beginTransaction();

            $execution_ok=$this->executeCheck24Order($order_xml);


            if(!$execution_ok){
                //Wenn was schief gelaufen ist, so speichern wir die Datei in dem dafür
                //vorgesehenen Ordner
                $folder=$this->customer . '/' . config('plattform-manager.check24.internal_import_folder_order_cancelled');
                Storage::disk('customers')->makeDirectory($folder);

                $time_stamp=date('ymdHisu');
                $file_path="{$folder}/{$time_stamp}.xml";

                //speichern
                copy(Storage::disk('customers')->path($file) ,Storage::disk('customers')->path($file_path));

                $this->info('Auftrag konnte nicht angenommen werden.');

            }

            //DB::rollback();

            //Ist alles gut verlaufen, bestätigen wir die Transaktion mit einem Commit
            DB::commit();

            //am Ende verschieben wir die Datei in einen Backupordner
            Storage::disk('customers')->makeDirectory("{$internal_import_folder_name}/backup");
            $time_stamp=date('ymdHisu');
            Storage::disk('customers')->move($file,"{$internal_import_folder_name}/backup/{$time_stamp}.xml");
        }
    }
    /**
     * Diese Funktion erhält den kompletten Auftrag.
     * Sie liefert als Ergebnis zurück, ob der Auftrag angenommen werden konnte oder abgelehnt werden muss.
     */
    protected function executeCheck24Order(SimpleXMLElement $xml_order){
        //Wir greifen uns erst mal die Order-Info
        $xml_order_header=$xml_order->{'ORDER_HEADER'};
        $xml_order_info=$xml_order_header->{'ORDER_INFO'};

        $check24_order_date_time=$xml_order_info->{'ORDER_DATE'};
        $check24_order_id=$xml_order_info->{'ORDER_ID'};

        //Jetzt die Beiteiligten nehmen, das ist einmal der Verkäufer, also einer unserer Visticle-Kunden, die Versandadresse(Kunde) und Check24()

        //Visticle-Kunde
        $xml_seller=null;
        $xml_check24=null;
        $xml_buyer=null;


        foreach($xml_order_info->PARTIES->PARTY as $xml_party){

            //Kunde- Käufer
            if($xml_party->{'PARTY_ID'}==(string)$xml_order_info->{'ORDER_PARTIES_REFERENCE'}->{'SUPPLIER_IDREF'}){
                $xml_seller=$xml_party;
            }
           //Visticle-Kunde - Verkäufer
           if($xml_party->{'PARTY_ID'}==(string)$xml_order_info->{'ORDER_PARTIES_REFERENCE'}->{'BUYER_IDREF'}){
                $xml_buyer=$xml_party;
            }
            //check24
           if($xml_party->{'PARTY_ID'}==(string)$xml_order_info->{'ORDER_PARTIES_REFERENCE'}->{'INVOICE_RECIPIENT_IDREF'}){
                $xml_check24=$xml_party;
            }
        }

        //Versand festlegen, das wird bei Check24 üblicherweise immer Versand sein!
        $shipment=Config_Shipment::query()->where('shipment_key','Standard DHL')->first();

        //Bezahlung festlegen
        $payment=null;

        //order status - ist immer auf "awaiting_fulfillment", also Bearbeitung ausstehend
        $order_status=Order_Status::query()->where('key','awaiting_fulfillment')->first();


        //Bezahldatum einholen!
        $payment_date=$xml_order_info->xpath('REMARK[@type=\'payment_date\']');
        if(count($payment_date)){
            $payment_date=(string)$payment_date[0];
        }
        else{
            $payment_date=null;
        }

        //Versandkosten einholen!
        $shipping_fee=$xml_order_info->xpath('REMARK[@type=\'shipping_fee\']');
        if(count($shipping_fee)){
            $shipping_fee=(string)$shipping_fee[0];
        }
        else{
            $shipping_fee=null;
        }

        //optionale Kundenmail holen
        $customer_mail=null;
        if($xml_buyer->ADDRESS->EMAIL){
            $customer_mail=(string)$xml_buyer->ADDRESS->EMAIL;
        }


        //Wir greifen die Order-Items ab, also unsere Artikel!
        $xml_order_items=$xml_order->{'ORDER_ITEM_LIST'};





        //Wir holen einfach die IDs, damit wir schnell feststellen können, ob der Auftrag angenommen werden kann.
        //Denn es können ja auch schwachsinnige Daten übermittelt worden sein.
        $xml_ids_check=$xml_order_items->xpath('./ORDER_ITEM/PRODUCT_ID/SUPPLIER_PID');



        $proofed=true;
        foreach($xml_ids_check as $xml_id_check){
            $xml_id_check=(string)$xml_id_check;

            $var=Article_Variation::query()->where('vstcl_identifier',$xml_id_check)->first();

            if($var==null){
                $proofed=false;
                break;
            }
        }


        //Wenn der Test nicht bestanden worden ist, müssen wir nicht weitermachen
        if($proofed==false){
            /**
             * Wir müssen die XML in eine BAckup-Datei schreiben
             */
            $this->info('Wir haben die Position nicht zuweisen können.');
            return false;
        }

        //Auftrag erstellen!!
        $orderCount = Order::count();
        $order = Order::create([
            'fk_config_payment_id' => $payment,
            'fk_config_shipment_id' => $shipment->id,
            'fk_order_status_id' => $order_status->id,
            'fk_provider_id' => $this->provider->id,
            'shipment_price' =>$shipping_fee,
            'voucher_price' => null,
            'number' => ++$orderCount,
            'email' => $customer_mail,
            'text' =>null
        ]);

        //Wir verknüpfen die Order noch mit den Adressen!
        $attribute_values=[];
        //Vorname
        if($xml_buyer->ADDRESS->NAME2){
            $attribute_values['billingaddress_vorname']=(string)$xml_buyer->ADDRESS->NAME2;
            $attribute_values['shippingaddress_vorname']=(string)$xml_buyer->ADDRESS->NAME2;
        }
         //Nachname
         if($xml_buyer->ADDRESS->NAME3){
            $attribute_values['billingaddress_nachname']=(string)$xml_buyer->ADDRESS->NAME3;
            $attribute_values['shippingaddress_nachname']=(string)$xml_buyer->ADDRESS->NAME3;
        }
        //Strasse
        if($xml_buyer->ADDRESS->STREET){
            $attribute_values['billingaddress_street']=(string)$xml_buyer->ADDRESS->STREET;
            $attribute_values['shippingaddress_street']=(string)$xml_buyer->ADDRESS->STREET;
        }
        //Zip
        if($xml_buyer->ADDRESS->ZIP){
            $attribute_values['billingaddress_postcode']=(string)$xml_buyer->ADDRESS->ZIP;
            $attribute_values['shippingaddress_postcode']=(string)$xml_buyer->ADDRESS->ZIP;
        }
        //City
        if($xml_buyer->ADDRESS->CITY){
            $attribute_values['billingaddress_city']=(string)$xml_buyer->ADDRESS->CITY;
            $attribute_values['shippingaddress_city']=(string)$xml_buyer->ADDRESS->CITY;
        }
         //Country
         if($xml_buyer->ADDRESS->COUNTRY){
            $attribute_values['billingaddress_region']=(string)$xml_buyer->ADDRESS->COUNTRY;
            $attribute_values['shippingaddress_region']=(string)$xml_buyer->ADDRESS->COUNTRY;
        }
         //Phone
         if($xml_buyer->ADDRESS->PHONE){
            $attribute_values['billingaddress_tel']=(string)$xml_buyer->ADDRESS->PHONE;
            $attribute_values['shippingaddress_tel']=(string)$xml_buyer->ADDRESS->PHONE;
        }
        //email
        if($xml_buyer->ADDRESS->EMAIL){
            $attribute_values['billingaddress_email']=(string)$xml_buyer->ADDRESS->EMAIL;
            $attribute_values['shippingaddress_email']=(string)$xml_buyer->ADDRESS->EMAIL;
        }
        //Gender - rudimentär
        if($xml_buyer->ADDRESS->{'CONTACT_DETAILS'}->TITLE){
            $attribute_values['billingaddress_gender']=(string)$xml_buyer->ADDRESS->{'CONTACT_DETAILS'}->TITLE;
            $attribute_values['shippingaddress_gender']=(string)$xml_buyer->ADDRESS->{'CONTACT_DETAILS'}->TITLE;
        }

        //Jetzt überführen wir die Attribute in die Datenbank
        foreach($attribute_values as $attribute => $value){
            $order->updateOrCreateAttribute($attribute,$value);
        }

        //Jetzt verknüpfen wir noch den Auftrag mit den Parteien
        $order->updateOrCreateAttribute('check24_buyer_party_id',(string)$xml_buyer->{'PARTY_ID'});
        $order->updateOrCreateAttribute('check24_seller_party_id',(string)$xml_seller->{'PARTY_ID'});
        $order->updateOrCreateAttribute('check24_check24_party_id',(string)$xml_check24->{'PARTY_ID'});

        //Jetzt nehmen wir noch die Check24 Order-ID und das Erstellungsdatum
        $order->updateOrCreateAttribute('check24_order_id',$check24_order_id);
        $order->updateOrCreateAttribute('check24_order_date_time',$check24_order_date_time);

        //Jetzt nehmen wir noch das Bezahldatum und die Versandkosten auf
        $order->updateOrCreateAttribute('check24_shipping_fee',$shipping_fee);
        $order->updateOrCreateAttribute('check24_payment_date',$payment_date);

        //jetzt erstellen wir noch eine Rechnung
        //Status holen, bezahlt
        $invoiceCount = Invoice::count();
        $invoice = Invoice::create([
            'fk_order_id' => $order->id,
            'fk_invoice_status_id' => 1,
            'number' => ++$invoiceCount
        ]);


        //Jetzt legen wir noch die Positionen an

        foreach($xml_order_items->{'ORDER_ITEM'} as $xml_order_item){
            $visticle_identifier=(string)$xml_order_item->{'PRODUCT_ID'}->{'SUPPLIER_PID'};

            $article_variation=Article_Variation::query()->where('vstcl_identifier',$visticle_identifier)->first();

            if(!$article_variation){
                var_dump($xml_order_item);
            }



            //Preis und Anzahl holen
            $price=(float)(string)$xml_order_item->{'PRODUCT_PRICE_FIX'}->{'PRICE_AMOUNT'};

            $quantity=(string)$xml_order_item->QUANTITY;
            //Steuer holen
            $tax=(float)(string)$xml_order_item->{'PRODUCT_PRICE_FIX'}->{'TAX_DETAILS_FIX'}->TAX;

            //dd(number_format(((float)$price / $quantity / ((float)$tax+1.0)) * 1,2));
            //dd(number_format(((float)$price / $quantity / ((float)$tax+1.0)) * 1,2));

            $orderArticle = OrderArticle::create([
                'fk_article_id' => $article_variation->article()->first()->id,
                'fk_article_variation_id' => $article_variation->id,
                'fk_order_id' => $order->id,
                'fk_orderarticle_status_id' => 1,
                'quantity' => $quantity,
                'tax' => $tax*100.00,
                'price' =>  ( (int)($price*100.00) / $quantity )
            ]);


            //Bestand an FEE übergeben!!
            $org_ean = $article_variation->getEan();
            $price = ($price / $quantity);
            $OrderNumber = "AN-".str_pad($order->number, 6, '0', STR_PAD_LEFT);
            $OrderNumberType = Setting::where('fk_settings_type_id','=','1')
                                ->first()->attributes()
                                ->where('name', '=', 'number_order')->first();
            if($OrderNumberType && $OrderNumberType!=null)
            { $OrderNumber = "AN-".str_pad($order->number, strlen((string) $OrderNumberType->value), '0', STR_PAD_LEFT); }
            $FeeOnlineBranch = Settings_Attribute::where('name','=','fee_online_branch_id')->first();
            if($FeeOnlineBranch){$FeeOnlineBranch=$FeeOnlineBranch->value;}
            $VerkaufsFilialie = $FeeOnlineBranch;
            $UrsprungsFilialie_id = (($article_variation->getFirstBranchWithStock())? $article_variation->getFirstBranchWithStock()->fk_branch_id : "");
            $branch = Branch::where('id', '=', $UrsprungsFilialie_id)->first();
            $UrsprungsFilialie="";
            if($branch){$UrsprungsFilialie=$branch->wawi_number;}

            $zaC = new ZalandoController();
            $zaC->fee_export($article_variation, $OrderNumber, 'V',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'check24');


            //bestände nun auch aktualisieren
            //dazu holen wir einfach alle Verknüpfungen aus Branch und Variation
            $variation_branches=$article_variation->branches()->where('stock','>','0')->get()->toArray();
            $index_variation_branches=0;
            $variation_branches_count=count($variation_branches);
            $stop=false;


            $quantity_kill=$quantity;
            if($variation_branches_count>0){
                do{
                    $variation_branch=$variation_branches[$index_variation_branches];

                    if($variation_branch['stock']<=$quantity_kill){
                        $quantity_kill-=$variation_branch['stock'];
                        $branch=Branch::find($variation_branch['fk_branch_id']);

                        //echo 'stock auf setzen : 0' ;
                        try{
                            //Ist der Kunde auf produktiv geschaltet?
                            $order_config=config('plattform-manager.check24.orders_productive');
                            if(in_array($this->customer,$order_config)){
                                $article_variation->updateOrCreateStockInBranch($branch,0);
                            }


                        }
                        catch(\Exception $e){
                            echo $e->getMessage();
                        }

                    }
                    else{
                        $difference=$variation_branch['stock']-$quantity_kill;
                        $branch=Branch::find($variation_branch['fk_branch_id']);

                        //echo $quantity_kill . ' -- branch-stock:'.$variation_branch['stock'].' --  stock auf setzen : ' . $difference;

                        try{
                            //$article_variation->updateOrCreateStockInBranch($branch,$difference);
                            //Ist der Kunde auf produktiv geschaltet?
                            $order_config=config('plattform-manager.check24.orders_productive');
                            if(in_array($this->customer,$order_config)){
                                $article_variation->updateOrCreateStockInBranch($branch,$difference);
                            }
                        }
                        catch(\Exception $e){
                            echo $e->getMessage();
                        }

                        $quantity_kill=0;
                    }

                    $index_variation_branches++;
                }
                while(
                         ($quantity_kill>0) &&
                         ($index_variation_branches < $variation_branches_count) &&
                         !$stop
                );
            }

        }

        //dd($xml_order_info->PARTIES);
        //dd([$xml_seller,$xml_check24,$xml_buyer]);
        //dd($xml_order_info);

        return true;
    }
    protected function importCheck24Orders(){
        //Einstellungen für den Kunden holen
        $plattform_manager=new PlattformManager($this->customer);
        $ftp_settings=$plattform_manager->getPlattformSettings($this->provider,['ftp'=>1]);

        //Wir holen den FTP-Ordnernamen für Auftragsdateien
        $import_folder=config('plattform-manager.check24.ftp.import_folder');

        //Dateiname für Orders bei check24
        $order_file_name=config('plattform-manager.check24.ftp.file_name_order');


        //FTP-Verbindung aufbauen
        $ftp_connection=\ftp_connect($ftp_settings['host'],$ftp_settings['port']);

        //FTP-Login
        $login_result=ftp_login($ftp_connection,$ftp_settings['user'],$ftp_settings['password']);

        //Loggen, wenn keine Verbindung aufgebaut werden konnte!
        if(!$ftp_connection || !$login_result){
            $this->info('Es konnte keine Verbindung aufgebaut werden!!');
            ftp_close($ftp_connection);
            return;
        }

         //in das Import-Verzeichnis wechseln
         ftp_chdir($ftp_connection,$import_folder);


        //Jetzt die Dateien holen!!!mehrere
        $file_list=ftp_nlist($ftp_connection,'.');


        //jetzt die Liste filtern!
        $filtered_file_list=preg_grep('/.*'.$order_file_name.'/',$file_list);
        $filtered_file_list_downloaded=[];





        //Ordnernamen für den internen Ordnernamen abgreifen
        $internal_import_folder_name=config('plattform-manager.check24.internal_import_folder_order');

        //Ordner erstellen falls es nicht existieren sollte!
        Storage::disk('customers')->makeDirectory("{$this->customer}/{$internal_import_folder_name}");

        //Jetzt starten wir einen Import für jede Datei!
        foreach($filtered_file_list as $filtered_file){
            //für jede Datei im Kunden-Ordner eine eigene Datei erstellen als Kopie von der jeweiligen Datei
            //aus dem Check24-Ftp-Ordner
            //Namen holen
            $file_name=date('YmdHisu') . '_' . config('plattform-manager.check24.ftp.file_name_order');

            //$file_path=Storage::disk('customers')->path("{$this->customer}/{$internal_import_folder_name}/{$file_name}");
            $file_path=Storage::disk('customers')->path("{$this->customer}/{$internal_import_folder_name}/{$filtered_file}");


            $file_transfer_status= ftp_nb_get($ftp_connection,$file_path,$filtered_file);

            While($file_transfer_status==FTP_MOREDATA){
                $file_transfer_status=ftp_nb_continue($ftp_connection);
            }
            if(!$file_transfer_status==FTP_FINISHED){

                //Eine Log schreiben
                continue;
            }
            else{
                //Jetzt setzen wir die Remote-Datei auf erfolgreich gedownloaded
                $filtered_file_list_downloaded[]=$filtered_file;
            }
        }

        //Bevor die Dateien dann verarbeitet werden, verschieben wir Dateien, die wir heruntergeladen haben,
        //bei Check24 in den Backupordner
        //Wir holen den Namen des Backup-Ordners
        $backup_folder=config('plattform-manager.check24.ftp.backup_folder');

        foreach($filtered_file_list_downloaded as $filtered_file_downloaded){

            $new_name=str_replace('.xml','',$filtered_file_downloaded) . '_backup.xml';
            ftp_rename($ftp_connection,$filtered_file_downloaded,"{$new_name}");

            //$command_result=ftp_exec($ftp_connection,"mv ./{$filtered_file_downloaded} ./../{$backup_folder}/{$filtered_file_downloaded}");

            //War das Kommando zum Verschieben der Dateien erfolreich? Wenn nicht, sollten wir einen Log schreiben
            //if($command_result){

            //}
            //else{
                //Loggen, dass das nicht geklappt hat!
            //}
        }




        //am Ende FTP-Verbindung schliessen
        ftp_close($ftp_connection);

        //Dem Plattformmanager mitteilen, dass der Export stattgefunden hat
        $plattform_manager->plattformTellsAction($this->provider,['action'=>'import_order','customer'=>$this->customer,'count'=>count($filtered_file_list_downloaded)]);


        return count($filtered_file_list_downloaded);
    }
}
