<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenant\Config_Payment, App\Tenant\Config_Shipment;
use App\Tenant\Setting;
use App\Tenant\Payment;
use App\Tenant\Invoice;
use App\Tenant\Voucher;
use App\Tenant\Category;
use App\Tenant\Article;
use App\Tenant\Article_Variation;
use App\Tenant\Customer;
use App\Tenant\Customer_Contacts; use App\Tenant\Customer_Attribute;
use App\Tenant\Order;
use App\Tenant\Order_Attribute;
use App\Tenant\OrderVoucher;
use App\Tenant\OrderVoucherArticle;
use App\Tenant\OrderVoucherCategory;
use App\Tenant\Order_Status;
use App\Tenant\OrderArticle;
use Redirect,Response;
use PDF;
use Validator, Session;
use Auth, Carbon\Carbon;
use Log;

use App\Tenant\Price_Customer_Articles;
use App\Tenant\Price_Customer_Categories;
use App\Tenant\Price_Groups_Customers;
use App\Tenant\Price_Groups;
use App\Tenant\Customer_Billing_Adresses;
use App\Tenant\Customer_Shipping_Adresses;
use App\Tenant\PaymentConditions;
use App\Tenant\PaymentConditionsCustomers;

use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;

class CustomerController extends Controller
{
    
    public function __construct()
    {
        //$this->middleware('auth');
    }
    
    public function index()
    {
        if(request()->ajax()) { return $this->dataTablesCustomerData(); }

        $customers = Customer::all();
        $content = [];
        foreach($customers as $customer) {
            $content[] = [ $customer->knr
                ,$customer->anrede." ".$customer->vorname." ".$customer->nachname
                ,$customer->telefon,$customer->mobil,$customer->fax
                ,$customer->email
                ,$customer->UStId
                ,$customer->zusatz_telefon,$customer->zusatz_email
                ,$customer->plz,$customer->ort
                ,$customer->text_info
            ];
        }

        return view('tenant.modules.customer.index.customer',  
        ['content' => $content
        ,'sideNavConfig' => Customer::sidenavConfig()            
        ] );
    }

    private function dataTablesCustomerData($selection = null, $columnconfig = 'customers') {
        $response = datatables()->of(
            (($selection) 
            ? $selection 
            : Customer::select('id','knr','idv_knr','email','anrede'
            , 'vorname'
            , 'nachname'
            , 'firma'
            ,'steuernummer'
            , 'telefon'
            , 'mobil'
            , 'fax'
            , 'UStId'
            , 'zusatz_telefon'
            , 'zusatz_email'
            ,'created_at')
            )
        )
        ->addColumn('action', 'action_button') 
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);

        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig($columnconfig);
       
        return json_encode($data);
    }

    public function create(){
        return view('tenant.modules.customer.create.customer', ['sideNavConfig' => Customer::sidenavConfig()]);
    }

    public function create_ansprechpartner($id)
    {
        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {
            $menu = [
                'general' => 'Stammdaten',
                'bestellungen' => 'Bestellungen',
                'ansprechpartner' => 'Ansprechpartner',
                'article_prices' => 'Artikelpreise',
                'category_vouchers' => 'Kategorie-Rabatte',
                'customer_global_prices' => 'Globale-Rabatte',
                'payment_conditions_customers' => 'Zahlungsbedingungen',
                ]; 
        }
        else
        {
            $menu = [
                'general' => 'Stammdaten',
                'ansprechpartner' => 'Ansprechpartner'
                ]; 
        }

        return view('tenant.modules.customer.create.customer_ansprechpartner', 
        [ 'customer_id' => $id
        , 'menu' => $menu
        , 'part' => 'ansprechpartner'
        , 'sideNavConfig' => Customer::sidenavConfig()
        ]);
    }

    public function show($id) {
       
    }
    
    public function show_ansprechpartner($customer_id, $ansprechpartner_id) 
    {
        $customer = Customer::where('id', $customer_id)->get()->first();
        $customer_ansprechpartner = Customer_Contacts::where('id', $ansprechpartner_id)->get()->first();
        
        $menu = [
            'general' => 'Stammdaten',
            'bestellungen' => 'Bestellungen',
            'ansprechpartner' => 'Ansprechpartner',
            'article_prices' => 'Artikelpreise',
            'category_vouchers' => 'Kategorie-Rabatte',
            'customer_global_prices' => 'Globale-Rabatte',
            'payment_conditions_customers' => 'Zahlungsbedingungen',
            ]; 

        return view('tenant.modules.customer.edit.edit_customer_ansprechpartner', 
        [ 'customer_id' => $customer_id
        , 'customer' => $customer
        , 'ansprechpartner_id' => $ansprechpartner_id
        , 'ansprechpartner' => $customer_ansprechpartner
        , 'menu' => $menu
        , 'part' => 'ansprechpartner'
        , 'sideNavConfig' => Customer::sidenavConfig()
        ]);
    }
    

    public function store(Request $request) 
    {
        if(isset($request->all()['billingaddr']))
        {
            if($request->all()['general']['email'] !== null)
            {   
                //Pre Check E-Mail unique bereits vergeben
                $customer_mail_count = Customer::all()->where('email','=',$request->all()['general']['email'])->count();
                if($customer_mail_count == 0)
                {
                    $customer_count = Customer::all()->count();

                    $billing_gender = '';
                    switch($request->all()['billingaddr']['gender'])
                    {
                        case 'billingaddr[gender][male]': $billing_gender = 'Herr'; break;
                        case 'billingaddr[gender][female]': $billing_gender = 'Frau'; break;
                    }

                    $general_gender = '';
                    switch($request->all()['general']['gender'])
                    {
                        case 'general[gender][male]': $general_gender = 'Herr'; break;
                        case 'general[gender][female]': $general_gender = 'Frau'; break;
                    }

                    $customer = Customer::create([
                        'knr' => str_pad($customer_count+1, 8, '0', STR_PAD_LEFT)
                        ,'anrede' => $general_gender
                        ,'idv_knr' => $request->all()['general']['idv_knr']
                        ,'vorname' => $request->all()['general']['first_name']
                        ,'nachname' => $request->all()['general']['last_name']
                        ,'UStId' => $request->all()['general']['UStId']
                        ,'steuernummer' => $request->all()['general']['steuernummer']
                        ,'firma' => $request->all()['general']['firma']                          
                        ,'telefon' => $request->all()['general']['telefon']
                        ,'zusatz_telefon' => $request->all()['general']['zusatz_telefon']
                        ,'mobil' => $request->all()['general']['mobil']
                        ,'fax' => $request->all()['general']['fax']
                        ,'email' => $request->all()['general']['email']
                        ,'zusatz_email' => $request->all()['general']['zusatz_email']
                        ,'text_info' => $request->all()['general']['text_info']
                    ]);
                    $billingaddr = Customer_Billing_Adresses::updateOrCreate(
                        [
                            'fk_customer_id' => $customer->id,                            
                        
                            'anrede' => $billing_gender,
                            'vorname' => $request->all()['billingaddr']['first_name'],
                            'nachname' => $request->all()['billingaddr']['last_name'],
                            'strasse_nr' => $request->all()['billingaddr']['street'],
                            'plz' => $request->all()['billingaddr']['postcode'],
                            'ort' => $request->all()['billingaddr']['city'],
                            'region' => $request->all()['billingaddr']['region'],
                        ]
                    );
    
                    if(!isset($request->all()['billinglikeshippingaddr']))
                    { 
                        $shipping_gender = '';
                        switch($request->all()['shippingaddr']['gender'])
                        {
                            case 'shippingaddr[gender][male]': $shipping_gender = 'Herr'; break;
                            case 'shippingaddr[gender][female]': $shipping_gender = 'Frau'; break;
                        }
                        $shippingaddr = Customer_Shipping_Adresses::updateOrCreate(
                        [
                            'fk_customer_id' => $customer->id, 
                       
                            'anrede' => $shipping_gender,
                            'vorname' => $request->all()['shippingaddr']['first_name'],
                            'nachname' => $request->all()['shippingaddr']['last_name'],
                            'telefon' => $request->all()['shippingaddr']['tel'],
                            'email' => $request->all()['shippingaddr']['email'],
                            'strasse_nr' => $request->all()['shippingaddr']['street'],
                            'plz' => $request->all()['shippingaddr']['postcode'],
                            'ort' => $request->all()['shippingaddr']['city'],
                            'region' => $request->all()['shippingaddr']['region'],
                        ]
                        );
                    }
                }else{return '"error":"true","message":"E-Mail bereits vergeben!"';}
                
            }else{return '"error":"true","message":"E-Mail ist ein Pflichfeld!"';}
            
        }
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }

    public function store_ansprechpartner(Request $request, $id) 
    {
        if(isset($request->all()['general']))
        { 
            $general_gender = '';
            switch($request->all()['general']['gender'])
            {
                case 'general[gender][male]': $general_gender = 'Herr'; break;
                case 'general[gender][female]': $general_gender = 'Frau'; break;
            }

            $customer_ansprechpartner = Customer_Contacts::create([
                 'fk_customer_id' => $id
                ,'anrede' => $general_gender
                ,'vorname' => $request->all()['general']['first_name']
                ,'nachname' => $request->all()['general']['last_name']
                ,'geburtstag' => $request->all()['general']['geburtstag']
                ,'position' => $request->all()['general']['position']
                ,'firma' => $request->all()['general']['firma']                          
                ,'telefon' => $request->all()['general']['telefon']
                ,'mobil' => $request->all()['general']['mobil']
                ,'fax' => $request->all()['general']['fax']
                ,'email' => $request->all()['general']['email']
                ,'text_info' => $request->all()['general']['text_info']
            ]);            
        }
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }

    public function update(Request $request, $id) 
    {
        $customer = Customer::where('id', $id)->get()->first();

        // CustomerController@article_prices_UpdateAjax
        
        if(isset($request->all()['billingaddr']))
        { 
            if($request->all()['general']['email'] !== null)
            {   
                //Pre Check E-Mail unique bereits vergeben
                $customer_mail_count = Customer::all()->where('email','=',$request->all()['general']['email'])
                ->where('id','!=',$id)->count();
                if($customer_mail_count == 0)
                {
                    $billing_gender = '';
                    switch($request->all()['billingaddr']['gender'])
                    {
                        case 'billingaddr[gender][male]': $billing_gender = 'Herr'; break;
                        case 'billingaddr[gender][female]': $billing_gender = 'Frau'; break;
                    }

                    $general_gender = '';
                    switch($request->all()['general']['gender'])
                    {
                        case 'general[gender][male]': $general_gender = 'Herr'; break;
                        case 'general[gender][female]': $general_gender = 'Frau'; break;
                    }

                    $customer = Customer::updateOrCreate(['id' => $id]
                        ,[                  
                            'anrede' => $general_gender
                            ,'idv_knr' => $request->all()['general']['idv_knr']
                            ,'vorname' => $request->all()['general']['first_name']
                            ,'nachname' => $request->all()['general']['last_name']
                            ,'UStId' => $request->all()['general']['UStId']
                            ,'steuernummer' => $request->all()['general']['steuernummer']
                            ,'firma' => $request->all()['general']['firma']                          
                            ,'telefon' => $request->all()['general']['telefon']
                            ,'zusatz_telefon' => $request->all()['general']['zusatz_telefon']
                            ,'mobil' => $request->all()['general']['mobil']
                            ,'fax' => $request->all()['general']['fax']
                            ,'email' => $request->all()['general']['email']
                            ,'zusatz_email' => $request->all()['general']['zusatz_email']
                            ,'text_info' => $request->all()['general']['text_info']
                    ]);


                    $billingaddr = Customer_Billing_Adresses::updateOrCreate(
                        [
                            'fk_customer_id' => $customer->id,                            
                        ],[
                            'anrede' => $billing_gender,
                            'vorname' => $request->all()['billingaddr']['first_name'],
                            'nachname' => $request->all()['billingaddr']['last_name'],
                            'strasse_nr' => $request->all()['billingaddr']['street'],
                            'plz' => $request->all()['billingaddr']['postcode'],
                            'ort' => $request->all()['billingaddr']['city'],
                            'region' => $request->all()['billingaddr']['region'],
                        ]
                    );
                    
                    if(!isset($request->all()['billinglikeshippingaddr']))
                    { 
                        
                        $shipping_gender = '';
                        switch($request->all()['shippingaddr']['gender'])
                        {
                            case 'shippingaddr[gender][male]': $shipping_gender = 'Herr'; break;
                            case 'shippingaddr[gender][female]': $shipping_gender = 'Frau'; break;
                        }
                        $shippingaddr = Customer_Shipping_Adresses::updateOrCreate(
                        [
                            'fk_customer_id' => $customer->id, 
                        ],[
                            'anrede' => $shipping_gender,
                            'vorname' => $request->all()['shippingaddr']['first_name'],
                            'nachname' => $request->all()['shippingaddr']['last_name'],
                            'telefon' => $request->all()['shippingaddr']['tel'],
                            'email' => $request->all()['shippingaddr']['email'],
                            'strasse_nr' => $request->all()['shippingaddr']['street'],
                            'plz' => $request->all()['shippingaddr']['postcode'],
                            'ort' => $request->all()['shippingaddr']['city'],
                            'region' => $request->all()['shippingaddr']['region'],
                        ]
                        );
                    }
                }else{return '"error":"true","message":"E-Mail bereits vergeben!"';}
                
            }else{return '"error":"true","message":"E-Mail darf nicht leer sein!"';}
            
        }
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }

    public function destroy($id) {

        $thisCustomer = Customer::where('id','=',$id)->first();
        $customer_billingadress = $thisCustomer->billing_adress()->delete();
        $customer_shippingadress = $thisCustomer->shipping_adress()->delete();
        $customer_contacts = $thisCustomer->contacts()->delete();
        $customer_payment_conditions = $thisCustomer->contacts()->delete();
        $customer_attributes = $thisCustomer->attributes()->delete();
        $customer_pricegroups = $thisCustomer->pricegroups()->delete();
        $customer_payment_conditions = $thisCustomer->payment_conditions()->delete();
        $customer_customer_article_prices = $thisCustomer->customer_article_prices()->delete();
        $customer_customer_category_vouchers = $thisCustomer->customer_category_vouchers()->delete();

        $thisCustomer->delete();
        $UpdateShop = $this->send_shop_data();
        return Response::json($thisCustomer);
    }

    public function delete_ansprechpartner(Request $request, $id, $ansprechpartner_id) 
    {
        $customer_contacts = Customer_Contacts::find($ansprechpartner_id)->delete();
        return Response::json($customer_contacts);
    } 
    
    
    public function priceRelUpdateAjax(Request $request) 
    {        
        $articleId = $request->articleId;
        $customerId = $request->customerId;
        $price_rel = $request->price_rel;
        $state = ($request->state == 'true');
        $rel_value = [];
        if($price_rel == "Individuell")
        { $rel = [ 'rel_type'  => $price_rel ,'rel_value'  => '0' ]; }
        else { $rel = [ 'rel_type'  => $price_rel ]; }

        if($state) 
        {
            $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
                [ 'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ]
                ,$rel
            );            
        }
        $UpdateShop = $this->send_shop_data();
        return response()->json(['success' => 1]);
    }

    public function berechne_Preise_neu($articleId,$customerId,$price_relValue) 
    {
        $Price_Customer_Article = Price_Customer_Articles::where('fk_customer_id','=',$customerId)
        ->where('fk_article_id','=',$articleId)->first();
        if($Price_Customer_Article)
        {   // Original Preise anpassen wenn Prozentual oder Festwert
            if($Price_Customer_Article->rel_type == "Prozentual"
            || $Price_Customer_Article->rel_type == "Festwert")
            {
                $newStandard = (float)str_replace(',', '.', $Price_Customer_Article->standard);
                $newDiscount = (float)str_replace(',', '.', $Price_Customer_Article->discount);
                $price_relValue = (float)str_replace(',', '.', $price_relValue);

                $articleStandardPrice = Article::find($articleId)->prices()->where('name','=','standard')->first();
                $checkPrice = (($articleStandardPrice)) ? (float)str_replace(',', '.', $articleStandardPrice->value) : 0;
                if($checkPrice > 0){$newStandard = $checkPrice;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleStandardPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','standard')->first();
                    $checkPrice = (($articleStandardPrice)) ? (float)str_replace(',', '.', $articleStandardPrice->value) : 0;
                    if( $checkPrice > 0 ){$newStandard = $checkPrice;}
                    else
                    {   // wenn keine WAWI Preise oder WaWi Preise == 0.00
                        $articleStandardPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','web_standard')->first();
                        $checkPrice = (($articleStandardPrice)) ? (float)str_replace(',', '.', $articleStandardPrice->value) : 0;
                        if( $checkPrice > 0 ){$newStandard = $checkPrice;}
                    }
                }

                $articleDiscountPrice = Article::with(['prices'])->find($articleId)->prices()->where('name','=','discount')->first();
                $checkPrice = (($articleDiscountPrice)) ? (float)str_replace(',', '.', $articleDiscountPrice->value) : 0;
                if($checkPrice > 0){$newDiscount = $checkPrice;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleDiscountPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','discount')->first();
                    $checkPrice = (($articleDiscountPrice)) ? (float)str_replace(',', '.', $articleDiscountPrice->value) : 0;
                    if($checkPrice > 0){$newDiscount =  $checkPrice;}
                    else
                    {   // wenn keine WAWI Preise oder WaWi Preise == 0.00
                        $articleDiscountPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','web_discount')->first();
                        $checkPrice = (($articleDiscountPrice)) ? (float)str_replace(',', '.', $articleDiscountPrice->value) : 0;
                        if( $checkPrice > 0 ){$newDiscount = $checkPrice;}
                    }
                }


                switch($Price_Customer_Article->rel_type)
                {
                    case "Prozentual":              
                        $newDiscount = (float)$newStandard+ (( (  ( ($newStandard*100) / 100)  ) * $price_relValue )/100);
                        //$newDiscount = (float)$newDiscount+ (( (  ( ($newDiscount*100) / 100)  ) * $price_relValue )/100);
                        //$newStandard = (float)$newStandard+ (( (  ( ($newStandard*100) / 100)  ) * $price_relValue )/100);
                        
                    break;
                    case "Festwert": 
                        $newDiscount = ($newDiscount+$price_relValue > 0)? $newDiscount+$price_relValue : "0.00";
                        $newStandard = ($newStandard+$price_relValue > 0)? $newStandard+$price_relValue : "0.00";                
                    break;
                }

                $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
                    [
                        'fk_article_id' => $articleId,                            
                        'fk_customer_id'  => $customerId
                    ]
                    ,[
                        'standard'  => (float)str_replace(',', '.', $newStandard),
                        'discount'  => (float)str_replace(',', '.', $newDiscount),
                    ]
                );
                $UpdateShop = $this->send_shop_data();
                return json_encode(['relValue'=>$price_relValue,'standard'=>str_pad(number_format( $newStandard , 2 ), 2, '0', STR_PAD_LEFT),'discount'=>str_pad(number_format( $newDiscount , 2 ), 2, '0', STR_PAD_LEFT)]);
            }            
        }
        return json_encode([]);
        
    }
    public function reset_org_Preise($articleId) 
    {
        $articleStandardPrice = Article::find($articleId)->prices()->where('name','=','standard')->first();
        if(($articleStandardPrice)){$articleStandardPrice = (float)str_replace(',', '.', $articleStandardPrice->value);}
        else
        { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
            $articleStandardPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','standard')->first();
            if(($articleStandardPrice)){$articleStandardPrice = (float)str_replace(',', '.', $articleStandardPrice->value);}
        }

        $articleDiscountPrice = Article::with(['prices'])->find($articleId)->prices()->where('name','=','discount')->first();
        if(($articleDiscountPrice)){$articleDiscountPrice = (float)str_replace(',', '.', $articleDiscountPrice->value);}
        else
        { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
            $articleDiscountPrice = Article::find($articleId)->variations()->first()->prices()->where('name','=','discount')->first();
            if(($articleDiscountPrice)){$articleDiscountPrice =  (float)str_replace(',', '.', $articleDiscountPrice->value);}
        }
        return json_encode(['standard'=>str_pad(number_format( $articleStandardPrice , 2 ), 2, '0', STR_PAD_LEFT),'discount'=>str_pad(number_format( $articleDiscountPrice , 2 ), 2, '0', STR_PAD_LEFT)]);
    }

    public function priceRelValueUpdateAjax(Request $request) {
        
        $articleId = $request->articleId;
        $customerId = $request->customerId;
        $price_relValue = $request->price_relValue;
        $rel_type = '';

        $Price_Customer_Article = Price_Customer_Articles::where('fk_customer_id','=',$customerId)
        ->where('fk_article_id','=',$articleId)->first();
        if($Price_Customer_Article)
        {
            if(empty($Price_Customer_Article->rel_type))
            {   // keine Relation zum Preis vorhanden, Eintrag wird gelöscht
                $Price_Customer_Article->delete();
                $UpdateShop = $this->send_shop_data();
                return response()->json(['success' => $this->reset_org_Preise($articleId) ]);                
            }else{$rel_type = $Price_Customer_Article->rel_type;}
            
        }
        if($price_relValue && $rel_type != "")
        {
            $indiPrices = $this->berechne_Preise_neu($articleId,$customerId,$price_relValue);

            $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
            [   'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ],
            [   'rel_value'  => (float)str_replace(',', '.', $price_relValue),
                'rel_type'  => $rel_type
            ]);
            $UpdateShop = $this->send_shop_data();
            return response()->json(['success' => $indiPrices]);
        }
        
    }

    public function StandardPriceUpdateAjax(Request $request) {
        
        $articleId = $request->articleId;
        $customerId = $request->customerId;
        $standardPrice = $request->standardPrice;
        $discountPrice = $request->discountPrice;

        $Price_Customer_Article = Price_Customer_Articles::where('fk_customer_id','=',$customerId)
        ->where('fk_article_id','=',$articleId)->first();
        if($Price_Customer_Article)
        {
            if(empty($Price_Customer_Article->rel_type))
            { 
                $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
                    [ 'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ]
                   ,[ 'rel_type'  => 'Individuell' ]
                );
            }
        }
        $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
            [ 'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ]
            ,[
                'standard'  => (float)str_replace(',', '.', $standardPrice),
                'discount'  => (float)str_replace(',', '.', $discountPrice),
            ]
        );
        $UpdateShop = $this->send_shop_data();
        return response()->json(['success' => json_encode(['standard'=>str_pad(number_format( (float)str_replace(',', '.', $standardPrice) , 2 ), 2, '0', STR_PAD_LEFT)]) ]);
    }

    public function DiscountPriceUpdateAjax(Request $request) {
        
        $articleId = $request->articleId;
        $customerId = $request->customerId;
        $standardPrice = $request->standardPrice;
        $discountPrice = $request->discountPrice;

        $Price_Customer_Article = Price_Customer_Articles::where('fk_customer_id','=',$customerId)
        ->where('fk_article_id','=',$articleId)->first();
        if($Price_Customer_Article)
        {
            if(empty($Price_Customer_Article->rel_type))
            { 
                $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
                    [ 'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ]
                   ,[ 'rel_type'  => 'Individuell' ]
                );
            }
        }
        $PriceCustomerArticle = Price_Customer_Articles::updateOrCreate(
            [ 'fk_article_id' => $articleId, 'fk_customer_id'  => $customerId ]
            ,[
                'standard'  => (float)str_replace(',', '.', $standardPrice),
                'discount'  => (float)str_replace(',', '.', $discountPrice),
            ]
        );
        $UpdateShop = $this->send_shop_data();
        return response()->json(['success' => json_encode(['discount'=>str_pad(number_format( (float)str_replace(',', '.', $discountPrice) , 2 ), 2, '0', STR_PAD_LEFT)]) ]);
    }

    
    public function deleteCustomerArticlePriceUpdateAjax($id, $article_id) 
    {
        $Price_Customer_Article = Price_Customer_Articles::where('fk_customer_id','=',$id)
        ->where('fk_article_id','=',$article_id)
        ->delete();
        $UpdateShop = $this->send_shop_data();
        return response()->json(['success' => $this->reset_org_Preise($article_id) ]);
    }
    
    

    public function article_prices_UpdateAjax(Request $request) {
        
    }

    public function edit_bestellungen_ajax($customerID) 
    {   $thismail = "999999999888888888777777777777";
        $customer = Customer::with(['attributes'])->where('id', $customerID)->get()->first();  
        if($customer){ if($customer->email != null && $customer->email != "")
        { $thismail = $customer->email; } }
        $response = datatables()->of(
            (($selection) 
            ? $selection 
            : Order::select('*')->with([
                'status', 
                'provider', 
                'provider.type', 
                'payment', 
                'shipment'
            ]))->where('email','=',$thismail)
        )
        ->addColumn('action', 'action_button') 
        ->addColumn('number', function(Order $order) {
            return Setting::getReceiptNameWithNumberByKey('order', $order->number);
        })
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);

        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig('customers_bestellungen');
       
        return json_encode($data);  
    }

    public function edit_article_prices_ajax($customerID) 
    {
        $PriceTypes = ['Prozentual','Individuell'];//,'Festwert'

        $response = datatables()->of(Article::with(['attributes', 'variations', 'prices'])
        ->select([ 'articles.id', 'articles.number', 'articles.name' ]))
        ->addColumn('systemnumber', function(Article $article) {
            if($article->id != null) {
                return Setting::getReceiptNameWithNumberByKey('article', $article->id);
            }
        })
        ->addColumn('var_eans', function(Article $article) {
            $vars = $article->variations()->get();
            $eans = '';
            foreach($vars as $var) {
                $eans .= str_replace('vstcl-','',$var->vstcl_identifier).' ';
            };
            return $eans;
        })
        ->addColumn('PriceTypes', $PriceTypes)
        ->addColumn('PriceType', function(Article $article) use ($customerID) {
            $articleCustomerPrices = $article->customerPrices($customerID)->first();
            if(is_object($articleCustomerPrices)){return $articleCustomerPrices->rel_type;}
            return '';
        })
        ->addColumn('RelValue', function(Article $article) use ($customerID) {
            $articleCustomerPrices = $article->customerPrices($customerID)->first();
            if(is_object($articleCustomerPrices)){return $articleCustomerPrices->rel_value;}
            return '';
        })
        ->addColumn('BaseStandardPrice', function(Article $article) use ($customerID) {            
                $articleStandardPrice = $article->prices()->where('name','=','standard')->first();
                if(($articleStandardPrice)){return $articleStandardPrice->value;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleStandardPrice = $article->variations()->first()->prices()->where('name','=','standard')->first();
                    if(($articleStandardPrice)){return $articleStandardPrice->value;}
                }            
            return '';
        })
        ->addColumn('BaseDiscountPrice', function(Article $article) use ($customerID) {
               $articleDiscountPrice = $article->prices()->where('name','=','discount')->first();
                if(($articleDiscountPrice)){return $articleDiscountPrice->value;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleDiscountPrice = $article->variations()->first()->prices()->where('name','=','discount')->first();
                    if(($articleDiscountPrice)){return $articleDiscountPrice->value;}
                }
            return '';
        })
        ->addColumn('StandardPrice', function(Article $article) use ($customerID) {
            $articleCustomerStandardPrice = $article->customerPrices($customerID)->first();
            if(is_object($articleCustomerStandardPrice)){return $articleCustomerStandardPrice->standard;}
            else
            {
                $articleStandardPrice = $article->prices()->where('name','=','standard')->first();
                if(($articleStandardPrice)){return $articleStandardPrice->value;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleStandardPrice = $article->variations()->first()->prices()->where('name','=','standard')->first();
                    if(($articleStandardPrice)){return $articleStandardPrice->value;}
                }
            }            
            return '';
        })
        ->addColumn('DiscountPrice', function(Article $article) use ($customerID) {
            $articleCustomerDiscountPrice = $article->customerPrices($customerID)->first();
            if(is_object($articleCustomerDiscountPrice)){return $articleCustomerDiscountPrice->discount;}
            else
            {   $articleDiscountPrice = $article->prices()->where('name','=','discount')->first();
                if(($articleDiscountPrice)){return $articleDiscountPrice->value;}
                else
                { // kein Artikelpreis vorhanden, rufe ersten Variationspreis ab
                    $articleDiscountPrice = $article->variations()->first()->prices()->where('name','=','discount')->first();
                    if(($articleDiscountPrice)){return $articleDiscountPrice->value;}
                }
            }   
            return '';
        })
        ->addColumn('action', 'action_button')
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);
        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig('customers_prices');
    
        return response()->json($data);
    }

    public function edit_ansprechpartner_ajax($customerID) 
    {
        $response = datatables()->of(Customer_Contacts::where('fk_customer_id','=',$customerID)
        ->select([ 'id', 'anrede', 'vorname', 'nachname', 'firma', 'geburtstag'
        , 'position', 'telefon', 'email'
        , 'mobil', 'fax', 'created_at' ]))
        
        ->addColumn('action', 'action_button')
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);
        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig('customers_ansprechpartner');
    
        return response()->json($data);
    }

    private function getStatusTabs() 
    {   $tabs = []; $statuses = Order_Status::all();
        foreach($statuses as $status) { $tabs [] = [ 'name' => $status->description ]; } 
        return $tabs;
    }

    public function edit($id, $part) 
    {
        $customer = Customer::with(['attributes'])->where('id', $id)->get()->first();    
        
        if(request()->ajax()) {
            switch($part)
            {
                case "bestellungen":
                    return $this->edit_bestellungen_ajax($id); 
                break;
                case "article_prices":
                    return $this->edit_article_prices_ajax($id); 
                break;
                case "ansprechpartner":
                    return $this->edit_ansprechpartner_ajax($id); 
                break;
                case "payment_conditions_customers":
                    return $this->dataTablesZahlungsbedingungenData();
                break;
                
            }
        }

        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        { 
            $menu = [
                'general' => 'Stammdaten',
                'bestellungen' => 'Bestellungen',
                'ansprechpartner' => 'Ansprechpartner',
                'article_prices' => 'Artikelpreise',
                'category_vouchers' => 'Kategorie-Rabatte',
                'customer_global_prices' => 'Globale-Rabatte',
                'payment_conditions_customers' => 'Zahlungsbedingungen',
                ]; 
        }
        else
        {
            $menu = [
                'general' => 'Stammdaten',
                'ansprechpartner' => 'Ansprechpartner'
                ]; 
        }
        
        $tabs=[];
        $thisView = 'general';
        $categories = [];
        $categories_ansprechpartner = [];
        $customer_category_vouchers = [];
        $selItems = [];
        $content = []; $price_groups =[]; $customer_vouchers=[];
        switch($part)
        {
            case "general":
                $thisView = 'customer';
            break;
            case "bestellungen":
                $thisView = 'bestellungen';
                $tabs = $this->getStatusTabs();
            break;
            case "ansprechpartner":
                $thisView = 'customer_ansprechpartner';
                $ansprechpartners = Customer_Contacts::where('fk_customer_id', '=', $id)->get();
                $categories_ansprechpartner = [];
                foreach($ansprechpartners as $ansprechpartner) {
                    $categories_ansprechpartner[] = [
                        $ansprechpartner->anrede
                        ,$ansprechpartner->vorname
                        ,$ansprechpartner->nachname
                        ,$ansprechpartner->firma
                        ,$ansprechpartner->telefon
                        ,$ansprechpartner->mobil
                        ,$ansprechpartner->fax
                        ,$ansprechpartner->email
                        ,$ansprechpartner->geburtstag
                        ,$ansprechpartner->position
                        ,$ansprechpartner->created_at
                    ];
                }
            break;
            case "article_prices":
                $thisView = 'customer_prices';
            break;
            case "category_vouchers":
                $thisView = 'customer_category_vouchers';
                $categories = Category::where('fk_wawi_id', '=', null)->get();
                $price_groups = Price_Groups::where('active', '=', 1)->get();
                $customer_category_vouchers = Price_Customer_Categories::where('fk_customer_id','=',$id)->get();
            break;
            case "customer_global_prices": 
                $thisView = 'customer_vouchers';
                $price_groups = Price_Groups::where('active', '=', 1)->get();
                $customer_vouchers = Price_Groups_Customers::where('customer_id','=',$id)->get();
            break;
            case "payment_conditions_customers": 
                $thisView = 'payment_conditions';
                $PaymentConditions = PaymentConditions::all();
                
                foreach($PaymentConditions as $PaymentCondition) {
                    $content[] = [
                        $PaymentCondition->id
                        ,$PaymentCondition->name
                        ,$PaymentCondition->condition
                        ,$PaymentCondition->id
                    ];
                }
                // selected Items
                $selItemsIDs = PaymentConditionsCustomers::where( [ 'fk_customer_id' => $id ] )->get()->pluck('fk_pcondition_id')->toArray();
                if($selItemsIDs){$selItems = PaymentConditions::whereIn('id', $selItemsIDs )->get();}
                
            break;
        }


        return view('tenant.modules.customer.edit.'.$thisView, 
        [ 'customer' => $customer
        , 'categories' => $categories
        , 'customer_vouchers' => $customer_vouchers
        , 'content' => $content
        , 'selItems' => $selItems
        , 'ansprechpartner' => $categories_ansprechpartner
        , 'customer_category_vouchers' => $customer_category_vouchers
        , 'price_groups' => $price_groups
        , 'part' => $part
        , 'menu'=> $menu  
        , 'tabs' => $tabs
        , 'sideNavConfig' => Customer::sidenavConfig()
        ]);
    }

    public function edit_ansprechpartner(Request $request, $id, $ansprechpartner_id) 
    {
        if(isset($request->all()['general']))
        { 
            $general_gender = '';
            switch($request->all()['general']['gender'])
            {
                case 'general[gender][male]': $general_gender = 'Herr'; break;
                case 'general[gender][female]': $general_gender = 'Frau'; break;
            }

            $customer_ansprechpartner = Customer_Contacts::updateOrCreate([
                 'fk_customer_id' => $id
                ,'id' => $ansprechpartner_id]
                ,[
                'anrede' => $general_gender
                ,'vorname' => $request->all()['general']['first_name']
                ,'nachname' => $request->all()['general']['last_name']
                ,'geburtstag' => $request->all()['general']['geburtstag']
                ,'position' => $request->all()['general']['position']
                ,'firma' => $request->all()['general']['firma']                          
                ,'telefon' => $request->all()['general']['telefon']
                ,'mobil' => $request->all()['general']['mobil']
                ,'fax' => $request->all()['general']['fax']
                ,'email' => $request->all()['general']['email']
                ,'text_info' => $request->all()['general']['text_info']
            ]);            
        }
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    } 
    // BEGINN Kunden Globale Rabatte
    public function vouchers($customerID)
    {
        $customer_vouchers = Price_Groups_Customers::where('customer_id','=',$customerID)->get();
        $price_groups = Price_Groups::where('active', '=', 1)->get();

        return view('tenant.modules.customer.edit.customer_vouchers',  
        ['customer_vouchers' => $customer_vouchers
        ,'price_groups' => $price_groups
        ,'sideNavConfig' => Customer::sidenavConfig()            
        ] );
    }
    public function vouchers_UpdateAjax(Request $request) 
    {        
        $customerId = $request->customerId;
        $price_groupID = $request->price_groupID;
        $state = ($request->state == 'true');        
        $price_group = Price_Groups::where('id', '=', $price_groupID)->first();

        $customer_voucher = Price_Groups_Customers::where('customer_id','=',$customerId)
        ->where('group_id','=',$price_groupID)->first();
        if($customer_voucher){return response()->json(['error' => 1 ]);}

        if($state && $customerId && $price_group) 
        {   $rel = [ 'rel_type'  => $price_group->val_type,'rel_value'  => $price_group->value ];
            $PriceGroupCustomer = Price_Groups_Customers::updateOrCreate(
            ['customer_id'  => $customerId,'group_id' => $price_group->id ] , $rel );
            $UpdateShop = $this->send_shop_data();
            return response()->json(['success' => 1]);
        }else{return response()->json(['error' => 1 ]);}        
    }
    public function deleteCustomerVoucherUpdateAjax($id, $price_group_id) 
    {
        if($id && $price_group_id)
        {   $PriceGroupCustomer = Price_Groups_Customers::where('customer_id','=',$id)
            ->where('group_id','=',$price_group_id)->first();
            if($PriceGroupCustomer)
            {   $PriceGroupCustomer->delete();
                $UpdateShop = $this->send_shop_data();
                return response()->json(['success' => 1 ]);
            }else{return response()->json(['error' => $PriceGroupCustomer ]);}        
        }
        else{return response()->json(['error' => 1 ]);}
    }
    // ENDE Kunden Globale Rabatte

    public function category_vouchers($customerID)
    {
        $categories = Category::where('fk_wawi_id', '=', null)->get();
        $customer_category_vouchers = Price_Customer_Categories::where('fk_customer_id','=',$customerID)->get();

        $price_groups = Price_Groups::where('active', '=', 1)->get();

        return view('tenant.modules.customer.edit.customer_category_vouchers',  
        ['categories' => $categories
        ,'customer_category_vouchers' => $customer_category_vouchers
        ,'price_groups' => $price_groups
        ,'sideNavConfig' => Customer::sidenavConfig()            
        ] );
    }
    public function category_vouchers_priceRelUpdateAjax(Request $request) 
    {        
        $categoryId = $request->categoryId;
        $customerId = $request->customerId;
        $price_groupID = $request->price_groupID;
        $state = ($request->state == 'true');        
        $price_group = Price_Groups::where('id', '=', $price_groupID)->first();
        $updatedCatIDs=[]; $selectedPriceGroup = "";
        if($state && $categoryId && $customerId && $price_group) 
        {   $selectedPriceGroup = $price_group->id;
            // Relation eintragen für Hauptkategorie
            $rel = [ 'rel_type'  => $price_group->val_type,'rel_value'  => $price_group->value,'fk_pricegroup_id' => $price_group->id ];
            $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(
            ['fk_category_id' => $categoryId,'fk_customer_id'  => $customerId ] , $rel );
            $updatedCatIDs[] = intval($categoryId);

            // Check der Kategorie nach Subkategorien            
            $thisCat = Category::where('id', '=', $categoryId)->first();
            if($thisCat)
            {   // 1. Ebene
                $thisHasSubCats = $thisCat->subcategories()->get();
                if(count($thisHasSubCats)>0)
                {   foreach($thisHasSubCats as $SubCat)
                    {   // SubCat prüfen ob bereits einen Rabatt besitzt
                        $checkRabatt = Price_Customer_Categories::where('fk_customer_id',  '=', $customerId)->where('fk_category_id',  '=', $SubCat->id)->first();
                        if(!$checkRabatt){ $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(['fk_category_id' => $SubCat->id,'fk_customer_id'  => $customerId ] , $rel ); $updatedCatIDs[] = $SubCat->id; }
                        
                        // 2. Ebene
                        $thisHasSubCats2 = $SubCat->subcategories()->get();
                        if(count($thisHasSubCats2)>0)
                        {   foreach($thisHasSubCats2 as $SubSubCat)
                            {   // SubSubCat prüfen ob bereits einen Rabatt besitzt
                                $checkRabatt = Price_Customer_Categories::where('fk_customer_id',  '=', $customerId)->where('fk_category_id',  '=', $SubSubCat->id)->first();
                                if(!$checkRabatt){ $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(['fk_category_id' => $SubSubCat->id,'fk_customer_id'  => $customerId ] , $rel ); $updatedCatIDs[] = $SubSubCat->id; }
                            
                                // 3. Ebene
                                $thisHasSubCats3 = $SubSubCat->subcategories()->get();
                                if(count($thisHasSubCats3)>0)
                                {   foreach($thisHasSubCats3 as $SubSubSubCat)
                                    {   // SubSubSubCat prüfen ob bereits einen Rabatt besitzt
                                        $checkRabatt = Price_Customer_Categories::where('fk_customer_id',  '=', $customerId)->where('fk_category_id',  '=', $SubSubSubCat->id)->first();
                                        if(!$checkRabatt){ $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(['fk_category_id' => $SubSubSubCat->id,'fk_customer_id'  => $customerId ] , $rel ); $updatedCatIDs[] = $SubSubSubCat->id; }

                                        // 4. Ebene
                                        $thisHasSubCats4 = $SubSubSubCat->subcategories()->get();
                                        if(count($thisHasSubCats4)>0)
                                        {   foreach($thisHasSubCats4 as $SubSubSubSubCat)
                                            {   // SubSubSubSubCat prüfen ob bereits einen Rabatt besitzt
                                                $checkRabatt = Price_Customer_Categories::where('fk_customer_id',  '=', $customerId)->where('fk_category_id',  '=', $SubSubSubSubCat->id)->first();
                                                if(!$checkRabatt){ $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(['fk_category_id' => $SubSubSubSubCat->id,'fk_customer_id'  => $customerId ] , $rel ); $updatedCatIDs[] = $SubSubSubSubCat->id; }

                                                // 5. Ebene
                                                $thisHasSubCats5 = $SubSubSubSubCat->subcategories()->get();
                                                if(count($thisHasSubCats5)>0)
                                                {   foreach($thisHasSubCats5 as $SubSubSubSubSubCat)
                                                    {   // SubSubSubSubSubCat prüfen ob bereits einen Rabatt besitzt
                                                        $checkRabatt = Price_Customer_Categories::where('fk_customer_id',  '=', $customerId)->where('fk_category_id',  '=', $SubSubSubSubSubCat->id)->first();
                                                        if(!$checkRabatt){ $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(['fk_category_id' => $SubSubSubSubSubCat->id,'fk_customer_id'  => $customerId ] , $rel ); $updatedCatIDs[] = $SubSubSubSubSubCat->id; }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Daten zum Shop senden
            $UpdateShop = $this->send_shop_data();
            // Upgedatete Kats an UI melden
            return response()->json(['success' => 1,'updated_cats'=> json_encode($updatedCatIDs),'selected_price_group'=> $selectedPriceGroup]);
        }else{return response()->json(['error' => 1 ]);}
        
    }

    public function category_vouchers_priceRelValueUpdateAjax(Request $request) {
        
        $categoryId = $request->categoryId;
        $customerId = $request->customerId;
        $price_groupID = $request->price_groupID;

        $Price_Customer_Category = Price_Customer_Categories::where('fk_customer_id','=',$customerId)
        ->where('fk_category_id','=',$categoryId)
        ->where('fk_pricegroup_id','=',$price_groupID)
        ->first();
        if($Price_Customer_Category)
        {
            if(empty($Price_Customer_Category->rel_type))
            {   // keine Relation zum Preis vorhanden, Eintrag wird gelöscht
                $Price_Customer_Category->delete();
                $UpdateShop = $this->send_shop_data();
                return response()->json(['deleted' => 1]);                
            }else{$rel_type = $Price_Customer_Category->rel_type;}            
        }
        $price_group = Price_Groups::where('id', '=', $price_groupID)->first();
        if($price_relValue && $rel_type != "" && $price_group)
        {
            $rel = [ 'rel_type'  => $price_group->val_type,'rel_value'  => $price_group->value,'fk_pricegroup_id' => $price_group->id ];
            $PriceCustomerCategory = Price_Customer_Categories::updateOrCreate(
            [ 'fk_category_id' => $categoryId, 'fk_customer_id'  => $customerId ],$rel);
            $UpdateShop = $this->send_shop_data();
            return response()->json(['success' => 1 ]);
        }else{return response()->json(['error' => 1 ]);}
        
    }
    public function deleteCustomerCategoryVoucherUpdateAjax($id, $category_id,$price_group_id) 
    {
        if($id && $category_id && $price_group_id)
        {
            $Price_Customer_Category = Price_Customer_Categories::where('fk_customer_id','=',$id)
            ->where('fk_category_id','=',$category_id)
            ->where('fk_pricegroup_id','=',$price_group_id)->first();
            if($Price_Customer_Category)
            {   $Price_Customer_Category->delete();
                $UpdateShop = $this->send_shop_data();
                return response()->json(['success' => 1 ]);
            }            
        }
        else{return response()->json(['error' => 1 ]);}
    }

    // Beginn Zahlungsbedingungen
    public function index_zahlungsbedingungen()
    {
        if(request()->ajax()) { return $this->dataTablesZahlungsbedingungenData(); }

        $PaymentConditions = PaymentConditions::all();
        $content = [];
        foreach($PaymentConditions as $PaymentCondition) {
            $content[] = [
                 $PaymentCondition->id
                ,$PaymentCondition->name
                ,$PaymentCondition->condition
                ,$PaymentCondition->id
            ];
        }       

        return view('tenant.modules.customer.index.zahlungsbedingungen',  
        ['content' => $content
        ,'sideNavConfig' => Customer::sidenavConfig()
            
        ] );
    }
    private function dataTablesZahlungsbedingungenData($selection = null, $columnconfig = 'zahlungsbedingungen') 
    {        
        $response = datatables()->of( (($selection)  ? $selection  : PaymentConditions::select('id','name','condition') ) )
        //->addColumn('Aktionen')->rawColumns(['Aktionen'])
        ->addIndexColumn()->make(true);
      
        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig($columnconfig);
       
        return json_encode($data);
    }
    public function store_zahlungsbedingungen(Request $request) 
    {
        if(isset($request->all()['condition']))
        {
            $condition = PaymentConditions::create([
                 'name' => ($request->all()['name'] == "")?" ":$request->all()['name']
                ,'condition' => $request->all()['condition']
            ]);
        }else{return redirect()->back()->withError('Fehler beim speichern!');}
        $UpdateShop = $this->send_shop_data();
        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }
    public function save_zahlungsbedingungen(Request $request, $id) 
    {
        $ThisCondition = PaymentConditions::where('id','=',$id)->first();
        if($ThisCondition)
        {
            if(isset($request->all()['condition']))
            {
                $ThisCondition->update([
                    'name' => ($request->all()['name'] == "")?" ":$request->all()['name']
                   ,'condition' => $request->all()['condition']
               ]);
            }
        }else{return '"error":"true","message":"Fehler beim speichern!"';}
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich gespeichert!"';
    }
    public function delete_zahlungsbedingungen(Request $request, $id) 
    {
        $ThisCondition = PaymentConditions::where('id','=',$id)->first();
        if($ThisCondition)
        {   // TODO Abhängigkeiten ebenfalls entfernen
            $ThisCondition->delete();
        }else{return '"error":"true","message":"Fehler beim löschen!"';}
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich gelöscht!"';
    }

    public function update_customer_zahlungsbedingungen(Request $request, $customer_id, $condition_id) 
    {
        $ThisCondition = PaymentConditions::where('id','=',$condition_id)->first();
        if($ThisCondition)
        {   PaymentConditionsCustomers::updateOrCreate( [ 'fk_pcondition_id' =>$condition_id, 'fk_customer_id' => $customer_id ] );
        }else{return '"error":"true","message":"Fehler beim zuweisen!"';}
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich zugewiesen!"';
    }
    public function delete_customer_zahlungsbedingungen(Request $request, $customer_id, $condition_id)
    {
        $ThisCustomerCondition = PaymentConditionsCustomers::where('fk_customer_id','=',$customer_id)->where('fk_pcondition_id','=',$condition_id)->first();
        if($ThisCustomerCondition){$ThisCustomerCondition->delete();}
        else{return '"error":"true","message":"Fehler beim löschen!"';}
        $UpdateShop = $this->send_shop_data();
        return '"success":"true","message":"Erfolgreich gelöscht!"';
    }

    public function unverified_user(Request $request)
    {
        $contents = $request->all();
        if(isset($contents['shop']) && isset($contents['email']) && $contents['email']!="")
        {
            // Kunde nach E-Mail finden
            $ThisKunde = Customer::where('email', '=', "".$contents['email']."")->first();
            if($ThisKunde)
            {}else
            {   // neuen Kunde erstellen wenn noch nicht vorhanden
                $anrede = ((isset($contents['anrede']) && $contents['anrede']!=""))? $contents['anrede'] : "";
                $vorname = ((isset($contents['vorname']) && $contents['vorname']!=""))? $contents['vorname'] : "";
                $nachname = ((isset($contents['nachname']) && $contents['nachname']!=""))? $contents['nachname'] : "";
                $firma = ((isset($contents['firma']) && $contents['firma']!=""))? $contents['firma'] : "";
                $telefon = ((isset($contents['telefon']) && $contents['telefon']!=""))? $contents['telefon'] : "";
                $strasse_nr = ((isset($contents['strasse_nr']) && $contents['strasse_nr']!=""))? $contents['strasse_nr'] : "";
                $plz = ((isset($contents['plz']) && $contents['plz']!=""))? $contents['plz'] : "";
                $ort = ((isset($contents['ort']) && $contents['ort']!=""))? $contents['ort'] : "";
                $region = ((isset($contents['region']) && $contents['region']!=""))? $contents['region'] : "";
                
                $customer_count = Customer::all()->count();
                $gender = '';
                switch($anrede)
                {   case 'male': $gender = 'Herr'; break;
                    case 'female': $gender = 'Frau'; break;
                    default: $gender = '-'; break;
                }

                $ThisKunde = Customer::updateOrCreate(
                    [ 'knr' => str_pad($customer_count+1, 8, '0', STR_PAD_LEFT)
                    , 'email' => $contents['email'] ],
                    [ 'anrede' => $gender,'vorname' => $vorname,'nachname' => $nachname
                    , 'firma' => $firma,'telefon' => $telefon
                    , 'zusatz_telefon' => "",'mobil' => "",'fax' => "",'zusatz_email' => "",'text_info' => "",'UStId' => "",'steuernummer' => "",'idv_knr' => ""
                    ]);
                $billingaddr = Customer_Billing_Adresses::updateOrCreate(
                    [ 'fk_customer_id' => $ThisKunde->id ],
                    [ 'anrede' => $gender,'vorname' => $vorname,'nachname' => $nachname
                    , 'strasse_nr' => $strasse_nr
                    , 'plz' => $plz,'ort' => $ort,'region' => $region
                    ]);
                // Kunden Attribute hinzufügen
                $AddAttr = Customer_Attribute::updateOrCreate([ 'fk_customer_id' => $ThisKunde->id, 'name' => 'user_unverified_order_for_shop'],[ 'value' => $contents['shop'] ] );
                $AddAttr = Customer_Attribute::updateOrCreate([ 'fk_customer_id' => $ThisKunde->id, 'name' => 'user_unverified_order_date'],[ 'value' => date("Y-m-d H:i:s") ] );
            } Log::info("(VSShop) Neuer VS Kunde - Bestellung - Unverifiziert: ".$contents['shop']." | ".$contents['email']." ||| ".json_encode($contents));
        }else{Log::info("Keine Daten in -unverified_user- ".json_encode($contents));}
    }
    public function verified_user(Request $request)
    {
        $contents = $request->all();
        if(isset($contents['shop']) && isset($contents['email']) && $contents['email']!="" && isset($contents['email_verified_at']))
        {
            // Kunde nach E-Mail finden
            $ThisKunde = Customer::where('email', '=', "".$contents['email']."")->first();
            if($ThisKunde)
            {}else
            {   // neuen Kunde erstellen wenn noch nicht vorhanden
                $anrede = ((isset($contents['anrede']) && $contents['anrede']!=""))? $contents['anrede'] : "";
                $vorname = ((isset($contents['vorname']) && $contents['vorname']!=""))? $contents['vorname'] : "";
                $nachname = ((isset($contents['nachname']) && $contents['nachname']!=""))? $contents['nachname'] : "";
                $firma = ((isset($contents['firma']) && $contents['firma']!=""))? $contents['firma'] : "";
                $telefon = ((isset($contents['telefon']) && $contents['telefon']!=""))? $contents['telefon'] : "";
                $strasse_nr = ((isset($contents['strasse_nr']) && $contents['strasse_nr']!=""))? $contents['strasse_nr'] : "";
                $plz = ((isset($contents['plz']) && $contents['plz']!=""))? $contents['plz'] : "";
                $ort = ((isset($contents['ort']) && $contents['ort']!=""))? $contents['ort'] : "";
                $region = ((isset($contents['region']) && $contents['region']!=""))? $contents['region'] : "";
                
                $customer_count = Customer::all()->count();
                $gender = '';
                switch($anrede)
                {   case 'male': $gender = 'Herr'; break;
                    case 'female': $gender = 'Frau'; break;
                    default: $gender = '-'; break;
                }

                $ThisKunde = Customer::updateOrCreate(
                    [ 'knr' => str_pad($customer_count+1, 8, '0', STR_PAD_LEFT)
                    , 'email' => $contents['email'] ],
                    [ 'anrede' => $gender,'vorname' => $vorname,'nachname' => $nachname
                    , 'firma' => $firma,'telefon' => $telefon
                    , 'zusatz_telefon' => "",'mobil' => "",'fax' => "",'zusatz_email' => "",'text_info' => "",'UStId' => "",'steuernummer' => "",'idv_knr' => ""
                    ]);
                $billingaddr = Customer_Billing_Adresses::updateOrCreate(
                    [ 'fk_customer_id' => $ThisKunde->id ],
                    [ 'anrede' => $gender,'vorname' => $vorname,'nachname' => $nachname
                    , 'strasse_nr' => $strasse_nr
                    , 'plz' => $plz,'ort' => $ort,'region' => $region
                    ]);
            }
            // Kunden Attribute hinzufügen
            $AddAttr = Customer_Attribute::updateOrCreate([ 'fk_customer_id' => $ThisKunde->id, 'name' => 'verified_for_shop'],[ 'value' => $contents['shop'] ] );
            $AddAttr = Customer_Attribute::updateOrCreate([ 'fk_customer_id' => $ThisKunde->id, 'name' => 'email_verified_at'],[ 'value' => $contents['email_verified_at']."|".$contents['shop'] ] );
            Log::info("(VSShop) Neuer VS Kunde verifiziert: ".$contents['shop']." | ".$contents['email']." > ".$contents['email_verified_at']." ||| ".json_encode($contents));
        }else{Log::info("Keine Daten in -verified_user- ".json_encode($contents));}
       
    }

    public function send_shop_data()
    {
        $shopController = new VSShopController();
        $shopController->shop_data(config()->get('tenant.identifier')); 
    }
    
    
    
}
