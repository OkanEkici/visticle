<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenant\Article;
use App\Tenant\Voucher;
use App\Tenant\Order;
use App\Tenant\Order_Attribute;
use App\Tenant\OrderVoucher;
use App\Tenant\OrderVoucherArticle;
use App\Tenant\OrderVoucherCategory;
use App\Tenant\Order_Status;
use App\Tenant\Setting;
use App\Tenant\Settings_Attribute;
use App\Tenant\Payment;
use App\Tenant\Invoice;
use App\Tenant\OrderArticle;
use App\Tenant\OrderArticle_Attribute;
use App\Tenant\Config_Payment, App\Tenant\Config_Shipment;
use App\Tenant\Article_Variation;
use Redirect,Response;
use PDF;
use Validator, Session;
use Auth, Carbon\Carbon;
use App\Mail\OrderConfirmed;
use App\Mail\OrderConfirmed_industry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Tenant;
use App\Tenant_Keys;
use App\Tenant\Customer;
use App\Http\Controllers\Tenant\Providers\Zalando\ZalandoController;
use App\Manager\Plattform\PlattformManager;
use App\Tenant\Branch;
use App\Tenant\Provider;

class OrderController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index()
    {   if(request()->ajax()) { return $this->dataTablesOrderData(); }
        $disallowedStatuses = ['refunded','shipped','awaiting_shipment'];
        return view('tenant.modules.order.index.order',
        [ 'provider_tabs' => $this->getProviderTabs()
        ,'tabs' => $this->getStatusTabs($disallowedStatuses)
        ,'sideNavConfig' => Order::sidenavConfig()
        ]);
    }

    private function getProviderTabs()
    {   $provider_tabs = [];  $providers = Provider::all();
        foreach($providers as $provider) { $provider_tabs[] = [ 'name' => $provider->name ]; }
        return $provider_tabs;
    }

    private function getStatusTabs($disallowedStatuses=false)
    {   $tabs = []; $statuses = Order_Status::all();
        if(!$disallowedStatuses){$disallowedStatuses = [];}
        foreach($statuses as $status) { if(!in_array($status->key,$disallowedStatuses)){$tabs [] = [ 'name' => $status->description ]; } }
        return $tabs;
    }

    public function create()
    { return view('tenant.modules.order.create.order', ['sideNavConfig' => Order::sidenavConfig()]); }

    public function edit($id) {
        $order = Order::where('id','=', $id)->first();
        return view('tenant.modules.order.edit.order', ['order' => $order, 'sideNavConfig' => Order::sidenavConfig()]);
    }

    public function show($id)
    {
        $identifier = config()->get('tenant.identifier');

        $order = Order::where('id','=', $id)->first();
        if(!$order){return redirect()->back();}
        $orderedArticlesContent = [];
        $articleCount = 0;
        $totalPrice = 0;
        $cleanTotalPrice = 0;
        $fmt = new \NumberFormatter( 'de_DE', \NumberFormatter::CURRENCY );
        $shipmentPrice = ($order->shipment_price) ? $order->shipment_price : 0;

        foreach($order->articles()->get() as $orderArticle)
        {
            $totalPrice += $orderArticle->price * $orderArticle->quantity;
            $article = $orderArticle->article()->first();
            $variation = $orderArticle->variation()->first();
            $color = '';
            $size = '';
            if($variation) {
                $size = $variation->getSizeText();
                $color = $variation->getColorText();
            }
            $orderedArticlesContent[$articleCount]['position'] = $articleCount + 1;
            $orderedArticlesContent[$articleCount]['name'] = $article->name;
            $orderedArticlesContent[$articleCount]['groesse'] = $size;
            $orderedArticlesContent[$articleCount]['farbe'] = $color;

            switch($identifier)
            {   case "demo1":
                    if($orderArticle->attributes()->first())
                    {   $orderArticleAttributes = $orderArticle->attributes()->get();
                        foreach($orderArticleAttributes as $A_Attribute)
                        {   switch($A_Attribute->name)
                            { case "hueftumfang":
                                    $orderedArticlesContent[$articleCount]['hueftumfang'] = $A_Attribute->value;
                              break;
                            }
                        }
                    }else{$orderedArticlesContent[$articleCount]['hueftumfang']='';}
                break;
                case "zoergiebel":
                    $Text_benutzerGroesse = ''; $Text_freitext = '';
                    if($orderArticle->attributes()->first())
                    {   $orderArticleAttributes = $orderArticle->attributes()->get();

                        foreach($orderArticleAttributes as $A_Attribute)
                        {   switch($A_Attribute->name)
                            {   case "Herren-Groesse":  $Text_benutzerGroesse .= (($Text_benutzerGroesse!='')?', ':'').'Herren-Größe: '.$A_Attribute->value; break;
                                case "Damen-Groesse":  $Text_benutzerGroesse .= (($Text_benutzerGroesse!='')?', ':'').'Damen-Größe: '.$A_Attribute->value; break;
                                case "Kinder-Groesse":  $Text_benutzerGroesse .= (($Text_benutzerGroesse!='')?', ':'').'Kinder-Größe: '.$A_Attribute->value; break;
                                case "freitext":  $Text_freitext = $A_Attribute->value; break;
                            }
                        }
                    }
                    $orderedArticlesContent[$articleCount]['benutzer-groesse'] = $Text_benutzerGroesse;
                    $orderedArticlesContent[$articleCount]['freitext'] = $Text_freitext;
                break;
            }
            $orderedArticlesContent[$articleCount]['ean'] = (($variation) ? $variation->getEan().(($variation->extra_ean!="")? ", ".$variation->extra_ean:'') : $article->ean);
            $orderedArticlesContent[$articleCount]['status'] = $orderArticle->status()->first()->description;
            $orderedArticlesContent[$articleCount]['price'] = $fmt->formatCurrency($orderArticle->price / 100, "EUR");
            $orderedArticlesContent[$articleCount]['quantity'] = $orderArticle->quantity;
            $articleCount++;
        }
        $cleanTotalPrice = $totalPrice;
        $voucherPrice = ($order->voucher_price) ? $order->voucher_price : 0;


        //dd($order->getShipmentAddress()->where('name','=','shipmentaddress_vorname')->first()->value);

        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {   $GesamtTax = 0; foreach ($order->getAllTaxPricesByKey($Tenant_type) as $key => $value)
            { $GesamtTax += $value*100; }
            //Log::info($GesamtTax." + ".$totalPrice." + ".$shipmentPrice." + ".(($shipmentPrice / 100) * \App\Helpers\VAT::getVAT()));
            $totalPrice = $totalPrice + $shipmentPrice + ($GesamtTax) - ($voucherPrice*100) ;
        }else{
            $totalPrice = $totalPrice + $shipmentPrice - ($voucherPrice*100);
        }

        return view('tenant.modules.order.show.order', [
            'order' => $order,
            'orderedArticlesContent' => $orderedArticlesContent,
            'orderDocuments' => $this->getProcessedOrderDocuments($order),
            'shipmentPrice' => $fmt->formatCurrency(($order->shipment_price) ? $order->shipment_price / 100 : 0, "EUR"),
            'cleanTotalPrice' => $fmt->formatCurrency($cleanTotalPrice / 100, "EUR"),
            'totalPrice_nr' => $totalPrice,
            'totalPrice' => $fmt->formatCurrency($totalPrice / 100, "EUR"),
            'billingAddrVals' => $this->buildAddressForOrder($order, 'billingaddress'),
            'shippingAddrVals' => ($order->isShippingLikeBilling() ? 'Lieferadresse ist gleich der Rechnungsadresse' : $this->buildAddressForOrder($order, 'shippingaddress')),
            'sideNavConfig' => Order::sidenavConfig(),
            'freitext' => ($order->text)?$order->text:"",
            'Tenant_type' => $Tenant_type
            ]);
    }

    public function store(Request $request) {
    }

    public function update(Request $request, $id) {
        $order = Order::where('id','=', $id)->first();
        $validator = Validator::make($request->billingaddr, [
            'vorname' => ['required', 'string', 'max:255'],
            'nachname' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            Session::flash('error', $validator->messages()->first());
            return redirect()->back()->withInput();
       }
        foreach($request->billingaddr as $billingAddrKey => $billingAddrVal)
        {   Order_Attribute::updateOrCreate(
            [ 'fk_order_id' => $order->id, 'name' =>  'billingaddress_'.$billingAddrKey ],
            [ 'value' => $billingAddrVal ] );
        }

        if(!isset($request->billinglikeshippingaddr)) {
            $validator = Validator::make($request->shippingaddr, [
                'vorname' => ['required', 'string', 'max:255'],
                'nachname' => ['required', 'string', 'max:255'],
                'street' => ['required', 'string', 'max:255'],
                'postcode' => ['required', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:255'],
            ]);
            if ($validator->fails()) {
                Session::flash('error', $validator->messages()->first());
                return redirect()->back()->withInput();
           }
        }
        foreach($request->shippingaddr as $shippingAddrKey => $shippingAddrVal)
        {   Order_Attribute::updateOrCreate(
            [ 'fk_order_id' => $order->id, 'name' =>  'shippingaddress_'.$shippingAddrKey ],
            [ 'value' => ($shippingAddrVal != null) ? $shippingAddrVal : '' ] );
        }
        Order_Attribute::updateOrCreate(
        [ 'fk_order_id' => $order->id, 'name' =>  'billingasshipping' ],
        [ 'value' => (isset($request->billinglikeshippingaddr) ? 'true' : 'false') ] );

        return redirect()->back()->withSuccess('Erfolgreich gespeichert!');
    }

    public function updateStatus($id, $status) {
        $order = Order::where('id','=', $id)->first();
        $status = Order_Status::where('key','=',$status)->first();
        if(!$status || !$order) {
            return redirect()->back()->withError('Es ist ein Fehler aufgetreten');
        }
        $order->fk_order_status_id = $status->id;
        $order->save();

        $Tenant = config()->get('tenant.identifier');
        $Tenant_allowed = ['demo1','basic'/*,'mukila'*/];
        // in_array($Tenant,$Tenant_allowed) &&
        if( $status->id == 10)
        {
            $invoiceCount = Invoice::count();
            $invoice = Invoice::create([
                'fk_order_id' => $order->id,
                'fk_invoice_status_id' => 1,
                'number' => ++$invoiceCount
            ]);
            $request= new Request();$result=$this->createAllDocuments($request, $order->id,true);
        }
        return redirect()->back();
    }
    public function floor3($val, $precision) {
        $precision = ($precision < 0) ? 0 : $precision;
        return round($val - (0.5 / pow(10, $precision)), $precision);
    }
    public function updatePayment(Request $request, $id) {
        $order = Order::where('id','=', $id)->first();
        $validator = Validator::make($request->all(), [
            'payed_price' => ['required', 'string'],
            'payed_date' => ['required', 'string', 'max:255']
        ]);
        if ($validator->fails()) {
            Session::flash('error', $validator->messages()->first());
            return redirect()->back()->withInput();
        }

       $price = (float)$request->payed_price;
       $date = date('Y-m-d', strtotime($request->payed_date));

       $Tenant_type = config()->get('tenant.tenant_type');
       if($Tenant_type=='vstcl-industry'){
        $totalPrice = ($order->getFullPrice_ind() / 100 );
       }
       else{
        $totalPrice = ($order->getFullPrice() / 100 ); // - (float)(($order->voucher_price)? $order->voucher_price : 0)
       }

       $payments = $order->payments();

       $alreadyPayed = 0;
       //check if payment fits in price to pay
       foreach($payments->get() as $payment) {
        	$alreadyPayed = (float)$alreadyPayed + (float)$payment->payment_amount;
       }

       if( (float)(sprintf("%01.2f", ($totalPrice - ($alreadyPayed + $price)) )) < 0) {
        Session::flash('error', 'Ihr eingegebener Preis ist höher als der noch offene Betrag. Offener Betrag: '.(number_format($totalPrice - $alreadyPayed, 2)).'€');
        return redirect()->back()->withInput();
       }

       Payment::create([
        'fk_order_id' => $order->id,
        'fk_config_payment_id' => 2,
        'payment_date' => $date,
        'payment_amount' => $price
       ]);


       if(number_format( (float)(sprintf("%01.2f", ($totalPrice - ($alreadyPayed + $price)) )), 2) <= 0)
       { $order->setStatus('awaiting_fulfillment'); }
       else { $order->setStatus('awaiting_payment'); }

       return redirect()->back()->withSuccess('Der Betrag wurde erfolgreich gebucht!');

    }

    public function getDocuments($id) {
        $order = Order::where('id','=', $id)->first();
        if(request()->ajax()) {
            return datatables()->of($this->getProcessedOrderDocuments($order))
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function articles(Request $request, $id) {
        $order = Order::where('id','=', $id)->first();
        $articles = $order->articles();
        if(request()->ajax()) {
            return datatables()->of($articles)
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function createDocument(Request $request, $id, $docType)
    {   dd($request);



        return redirect()->back()->withSuccess('Erfolgreich erstellt!');
    }

    public function createAllDocuments(Request $request, $id,$intern=false)
    {
        $orderId = $id;
        $thisTenant = config()->get('tenant');
        $pdf = null; $pdfName = '';
        $order = Order::find($orderId);
        $pdfPath = storage_path()."/customers/".config()->get('tenant.identifier')."/pdf/";

        $AB_Nummer = Order_Attribute::where('fk_order_id', '=', $orderId)->where('name', '=', 'AB_Nr')->first();
        if($AB_Nummer){$AB_Nummer = $AB_Nummer->value;}
        $docTypes = ['invoice','retoure','delivery_note','order_confirmation','packing'];
        $invoice = $order->invoices()->first();

        foreach($docTypes as $docType)
        {   $continue=false;
            switch($docType) {
                case 'invoice':
                    if(!$invoice) {$continue=true; break; }
                    $pdfName = Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number).'.pdf';
                break;
                case 'retoure': if(!$invoice) {$continue=true; break; } $pdfName = 'retoureschein-'.$order->number.'.pdf'; break;
                case 'delivery_note': $pdfName = Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number).'.pdf'; break;
                case 'order_confirmation': $pdfName = $AB_Nummer.'.pdf'; break;
                case 'packing': $pdfName = Setting::getReceiptNameWithNumberByKey('packing', $order->number).'.pdf'; break;
            }
            if($continue){continue;}

            if(!file_exists($pdfPath.$pdfName)) {
                switch($docType) {
                    case 'invoice': $pdf = $this->pdfRechnung($orderId, $invoice); break;
                    case 'retoure': $pdf = $this->pdfRetoure($orderId); break;
                    case 'delivery_note': $pdf = $this->pdfLieferschein($orderId); break;
                    case 'order_confirmation': $pdf = $this->pdfBestellbestaetigung($orderId, $AB_Nummer, $thisTenant['identifier']); break;
                    case 'packing': $pdf = $this->pdfPackschein($orderId); break;
                }

                $pdf->save($pdfPath.$pdfName);
            }
        }
        if($intern){return "1";}
        return redirect()->back()->withSuccess('Erfolgreich erstellt!' );
    }

    public function downloadDocument(Request $request, $orderId, $docType) {

        $thisTenant = config()->get('tenant');
        $pdf = null; $pdfName = '';
        $order = Order::find($orderId);
        $pdfPath = storage_path()."/customers/".$request->attributes->get('identifier')."/pdf/";

        $AB_Nummer = Order_Attribute::where('fk_order_id', '=', $orderId)->where('name', '=', 'AB_Nr')->first();
        if($AB_Nummer){$AB_Nummer = $AB_Nummer->value;}

        switch($docType) {
            case 'invoice':
                $invoice = $order->invoices()->first();
                if(!$invoice) { return false; }
                $pdfName = Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number).'.pdf';
            break;
            case 'retoure': $pdfName = 'retoureschein-'.$order->number.'.pdf'; break;
            case 'delivery_note': $pdfName = Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number).'.pdf'; break;
            case 'order_confirmation': $pdfName = $AB_Nummer.'.pdf'; break;
            case 'packing': $pdfName = Setting::getReceiptNameWithNumberByKey('packing', $order->number).'.pdf'; break;
            default: break;
        }

        if(!file_exists($pdfPath.$pdfName)) {
            switch($docType) {
                case 'invoice': $pdf = $this->pdfRechnung($orderId, $invoice); break;
                case 'retoure': $pdf = $this->pdfRetoure($orderId); break;
                case 'delivery_note': $pdf = $this->pdfLieferschein($orderId); break;
                case 'order_confirmation': $pdf = $this->pdfBestellbestaetigung($orderId, $AB_Nummer, $thisTenant['identifier']); break;
                case 'packing': $pdf = $this->pdfPackschein($orderId); break;
                default: break;
            }
            $pdf->save($pdfPath.$pdfName);
        }
        return response()->download($pdfPath.$pdfName);
    }

    public function pdfRechnung($id, Invoice $invoice) {
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        $billingAddr = $this->getBillingAddr($order);
        $payment = $order->payments()->first();
        if($order->isShippingLikeBilling()) { $shippingAddr = $this->getBillingAddr($order); }
        else { $shippingAddr = $this->getShipmentAddr($order); }

        $data = [
            'positions' => [
                'columns' => ['Artikel-Nr.', 'Artikelbezeichnung', 'Menge', 'Einzelpreis', 'Summe'],
                'rows' => []
            ]
        ];

        $articles = $order->articles()->get();
        $count = 0;
        foreach($articles as $article) {
            $count++;
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) {
                continue;
            }
            $data['positions']['rows'][] = [
                'values' => [
                    $mainArticle->number,
                    $mainArticle->name,
                    $article->packed,
                    Article::getFormattedPrice($article->price / 100),
                    Article::getFormattedPrice($article->price * $article->packed / 100)
                ]
            ];
        }


        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();
        $pdf = PDF::loadView('tenant.pdf.rechnung', [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data, 'order' => $order,
            'shippingAddr' => $shippingAddr,
            'billingAddr' => $billingAddr,
            'payment' => $payment,
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number),
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            ])
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-html', $footerHtml);
        return $pdf;
    }

    public function pdfRechnungKorrektur($id) {
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        $shippingAddr = $this->getShipmentAddr($order);
        $data = [
            'positions' => [
                'columns' => ['Artikel-Nr.', 'Artikelbezeichnung', 'Menge', 'Artikelbezeichnung', 'Einzelpreis', 'Summe'],
                'rows' => []
            ]
        ];
        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();
        $pdf = PDF::loadView('tenant.pdf.rechnungskorrektur', [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data, 'order' => $order,
            'shippingAddr' => $shippingAddr,
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('invoice', $order->number)
            ])
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-html', $footerHtml);
        return $pdf;
    }

    public function pdfLieferschein($id){
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        if($order->isShippingLikeBilling()) { $shippingAddr = $this->getBillingAddr($order); }
        else { $shippingAddr = $this->getShipmentAddr($order); }

        $data = [ 'positions' => [
            'columns' => ['Artikel-Nr.','Artikelbezeichnung','Menge','Einzelpreis €','Summe €'],
            'rows' => [] ]
        ];
        $articles = $order->articles()->get();
        $count = 0;
        foreach($articles as $article) {
            $count++;
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) { continue; }
            $data['positions']['rows'][] = [
                'values' => [
                    $mainArticle->number,
                    $mainArticle->name,
                    $article->packed,
                    Article::getFormattedPrice($article->price / 100),
                    Article::getFormattedPrice($article->price * $article->packed / 100)
                ]
            ];
        }

        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();
        $pdf = PDF::loadView('tenant.pdf.lieferschein', [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data,
            'order' => $order,
            'shippingAddr' => $shippingAddr,
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number)
            ])
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-html', $footerHtml);
        return $pdf;
    }

    public function pdfBestellbestaetigung($id,$AB_Nummer, $thisTenant = ""){
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        $billingAddr = $this->getBillingAddr($order);

        $data = $this->GetBestellData_IND($order->id, $AB_Nummer);

        // Zahlungsbedingungn für den Käufer einholen
        $Paymentconditions = [];
        $Kundennummer = "";
        $isCustomer = Customer::where('email','=',$order->email)->first();
        if($isCustomer)
        {   $Kundennummer = ($isCustomer->idv_knr !== null && $isCustomer->idv_knr != "")? $isCustomer->idv_knr : $isCustomer->knr;
            $hasPaymentConditions = $isCustomer->payment_conditions()->get();
            if($hasPaymentConditions && count($hasPaymentConditions) > 0)
            {   foreach($hasPaymentConditions as $PaymentCondition)
                {   $Condition = PaymentConditions::where('id','=',$PaymentCondition->fk_pcondition_id)->first();
                    if($Condition){$Paymentconditions[]=$Condition->condition;}
                }
            }
        }

        $payment = Config_Payment::where('id', '=', $order->fk_config_payment_id)->first();

        $fmt = new \NumberFormatter( 'de_DE', \NumberFormatter::CURRENCY );
        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();
        $pdf = PDF::loadView('tenant.pdf.bestellbestaetigung', [
            'Paymentconditions' => $Paymentconditions,
            'payment' => $payment,
            'shipmentPrice' => $fmt->formatCurrency(($order->shipment_price) ? $order->shipment_price / 100 : 0, "EUR"),
            'Kundennummer' => $Kundennummer,
            'Tenant' => ($thisTenant!="")? $thisTenant : config()->get('tenant.identifier'),
            'data' => $data,
            'order' => $order,
            'billingAddr' => $billingAddr,
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number),
            'AB_Nummer' => $AB_Nummer
            ])
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-html', $footerHtml);
        return $pdf;
    }

    protected function pdfPachschein_check24($id){
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        $shippingAddr = $this->getShipmentAddr($order);

        $data =[];

        $articles = $order->articles()->get();

        foreach($articles as $article) {
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) { continue; }

            $data['positions'][] = [

                    'img' => (($varArticle->getThumbnailBigImg()->first()) ? public_path('storage'.$varArticle->getThumbnailBigImg()->first()->location) : '') ,
                    'color' => $varArticle->getColorText(),
                    'size' => $varArticle->getSizeText(),
                    'length' => $varArticle->getLengthText(),
                    'brand' => $mainArticle->getAttrByName('hersteller'),
                    'name' => $mainArticle->name,
                    'number' => $mainArticle->number,
                    'ean' => $varArticle->getEan(),
                    'quantity' => $article->quantity,
                    'price' => number_format($article->price / 100.00, 2),
            ];
        }


        //Jetz holen wir uns in Abhängigkeit vom Kunden noch die konfigurierte View
        $plattform_manager=new PlattformManager();
        $settings=$plattform_manager->getPlattformSettings($order->provider,['receipts'=>true]);
        $subdomain=config()->get('tenant.identifier');
        $view=$settings['receipts']['picklist']['view'];







        $pdf = PDF::loadView($view, [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data,
            'order' => $order,
            'tel' => $order->getBillingAddressByKey('phone_number'),
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'orderDate' => date('d.m.Y H:i:s', strtotime($order->created_at)),
            'shippingAddr' => $shippingAddr,
            //'billingAddr' => $billingAddr,
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('packing', $order->number),
            'receipt_type' => 'picklist'
            ])
            ->setOption('orientation', 'Portrait')
            ->setOption('enable-javascript',true)
            ->setOption('enable-local-file-access',true)
            ->setOption('user-style-sheet',public_path('css/app.css'));
            //->setOption('margin-bottom', '30mm')
            //->setOption('footer-html', $footerHtml);

            //dd($pdf);


        return $pdf;
    }
    public function pdfPackschein($id) {
        $order = Order::where('id','=', $id)->first();


        /**
         * @author Tanju Özsoy
         * Wir schauen hier, ob die Bestellung von Check24 kommt. Wenn ja übernimmt
         * eine andere Methode die Erstellung des Packscheins.
         */
        $provider=$order->provider;
        if($provider->type->provider_key=='check24')
        {
            //return $this->pdfPachschein_check24($id);
        }


        $companyAddr = $this->getCompanyAddr();
        $shippingAddr = $this->getShipmentAddr($order);
        $billingAddr = $this->getBillingAddr($order);
        $data =
        [   'positions' =>
            [   'columns' => [
                    'Filiale', 'Platz', 'Vorschaubild'
                    , 'Farbe', 'Größe', 'Länge'
                    , 'Lieferant', 'Artikelbezeichnung'
                    , 'Artikel-Nr.', 'EAN', 'Menge' , 'i.O.'
                ], 'rows' => []
            ]
        ];

        $articles = $order->articles()->get();
        $count = 0;
        foreach($articles as $article) {
            $count++;
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) { continue; }

            $data['positions']['rows'][] = [
                'values' => [
                    '', '',
                    'img' => (($varArticle->getThumbnailBigImg()->first()) ? '<img src="'.public_path('storage'.$varArticle->getThumbnailBigImg()->first()->location).'" style="max-height:70px; max-width: 70px;">' : '') ,
                    'color' => $varArticle->getColorText(),
                    'size' => $varArticle->getSizeText(),
                    'length' => $varArticle->getLengthText(),
                    $mainArticle->getAttrByName('hersteller'),
                    $mainArticle->name,
                    $mainArticle->number,
                    $varArticle->getEan(),
                    $article->quantity,
                    '<input type="checkbox">'
                ]
            ];
        }

        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();

        $pdf = PDF::loadView('tenant.pdf.packschein', [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data,
            'order' => $order,
            'tel' => $order->getBillingAddressByKey('phone_number'),
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'orderDate' => date('d.m.Y H:i:s', strtotime($order->created_at)),
            'shippingAddr' => $shippingAddr,
            'billingAddr' => $billingAddr,
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('packing', $order->number)
            ])
            ->setOption('orientation', 'Landscape')
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-html', $footerHtml);

        return $pdf;
    }

    public function pdfRetoure($id){
        $order = Order::where('id','=', $id)->first();
        $companyAddr = $this->getCompanyAddr();
        $billingAddr = $this->getBillingAddr($order);
        if($order->isShippingLikeBilling()) {
            $shippingAddr = $this->getBillingAddr($order);
        }
        else {
            $shippingAddr = $this->getShipmentAddr($order);
        }

        $data = [
            'positions' => [
                'columns' => ['Artikel', 'Beschreibung', 'Geliefert', 'Reklamation'],
                'rows' => []
            ]
        ];
        $articles = $order->articles()->get();
        $count = 0;

        foreach($articles as $article) {
            $count++;
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) { continue; }

            $data['positions']['rows'][] = [
                'values' => [
                    $mainArticle->number,
                    $mainArticle->name,
                    $article->packed,
                    '<table>
                        <tr style="height: 20px;">
                            <td style="padding: 0 20px 0 0!important;">Anzahl:</td>
                            <td style="padding: 0 0 0 0!important;">Grund:</td>
                        </tr>
                        <tr style="height: 20px;">
                            <td style="padding: 0 20px 0 0 !important;">Ersatz: <input type="checkbox"></td>
                            <td style="padding: 0 0 0 0 !important;">Gutschrift: <input type="checkbox"></td>
                        </tr>
                    </table>',
                ]
            ];
        }
        $invoice = $order->invoices()->first();
        $footerHtml = view()->make('tenant.pdf.partials.footer')->render();
        $pdf = PDF::loadView('tenant.pdf.retoure', [
            'Tenant' => config()->get('tenant.identifier'),
            'data' => $data, 'order' => $order,
            'billingAddr' => $billingAddr,
            'shippingAddr' => $shippingAddr,
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'companyAddr' => $companyAddr,
            'number' => Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number)
            ])
            ->setOption('margin-bottom', '30mm')
            ->setOption('footer-right', 'Seite [page]/[topage]')
            ->setOption('footer-html', $footerHtml);
        return $pdf;
    }

    public function pdfWiderruf($id) {
        $order = Order::where('id','=', $id)->first();
        $data = [
            'Tenant' => config()->get('tenant.identifier'),
            'title' => 'First PDF for Medium',
            'heading' => 'Hello from 99Points.info',
            'content' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industrys standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.'
        ];

        $pdf = PDF::loadView('tenant.pdf.retoure', $data);
        return $pdf->download($order->id.'-retoure.pdf');
    }

    protected function getProcessedOrderDocuments(Order $order)
    {
        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        {   $orderDocs = [
             'delivery_note' => ['title' => 'Lieferschein']
            ,'order_confirmation' => ['title' => 'Auftragsbestätigung']
            ,'packing' => ['title' => 'Packschein']
            ,'invoice' => ['title' => 'Rechnung']
            ,'retoure' => ['title' => 'Retoureformular']
            ];
        }
        else{
            $orderDocs = [
             'delivery_note' => ['title' => 'Lieferschein']
            ,'packing' => ['title' => 'Packschein']
            ,'invoice' => ['title' => 'Rechnung']
            ,'retoure' => ['title' => 'Retoureformular']
        ];
        }

        $tableRows = [];
        foreach($orderDocs as $key => $doc) {
            $number = $order->number; $name = '';
            if($key == 'invoice') { $invoice = $order->invoices()->first(); if(!$invoice) { continue; } $number = $invoice->number; }

            if($key == 'retoure') { $name = 'Retoureschein'; }
            else if($key == 'order_confirmation') {
                $AB_Nummer = Order_Attribute::where('fk_order_id', '=', $order->id)->where('name', '=', 'AB_Nr')->first();
                if($AB_Nummer){$AB_Nummer = $AB_Nummer->value;$name = $AB_Nummer.""; }
            }
            else { $name = Setting::getReceiptNameWithNumberByKey($key, $number); }

        $showLinks = ['order_confirmation','invoice','packing'/*,'retoure'*/];
            $pdf = null; $pdfName = ''; $pdfPath = storage_path()."/customers/".config()->get('tenant.identifier')."/pdf/";

            $AB_Nummer = Order_Attribute::where('fk_order_id', '=', $order->id)->where('name', '=', 'AB_Nr')->first();
            if($AB_Nummer){$AB_Nummer = $AB_Nummer->value;}
            $invoice = $order->invoices()->first();

            switch($key) {
                case 'invoice':
                    if(!$invoice) { break; }
                    $pdfName = Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number).'.pdf';
                break;
                case 'retoure': if(!$invoice) { break; } $pdfName = 'retoureschein-'.$order->number.'.pdf'; break;
                case 'delivery_note': $pdfName = Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number).'.pdf'; break;
                case 'order_confirmation': $pdfName = $AB_Nummer.'.pdf'; break;
                case 'packing': $pdfName = Setting::getReceiptNameWithNumberByKey('packing', $order->number).'.pdf'; break;
            }

            $tableRows[] =
            [   ($order->isFulfilled()) ? '<a href="'.route('tenant.orders.pdf.downloadDocument', [config('tenant.identifier'), $order->id, $key]).'">'.$name.'</a>'
            : ((in_array($key, $showLinks) && $name != "" && file_exists($pdfPath.$pdfName) )? '<a href="'.route('tenant.orders.pdf.downloadDocument', [config('tenant.identifier'), $order->id, $key]).'">'.$name.'</a>'
            : '' )
            , $doc['title'] ];
        }
        return $tableRows;
    }

    protected function buildAddressForOrder($order, $key) {
        $addrSameRow = ['nachname', 'city'];
        $addrWithTitle = ['email', 'phone_number'];
        $addr = []; $addrVals = [];
        $genderVals = ['m' => 'Herr', 'w' => 'Frau', 'male' => 'Herr', 'woman' => 'Frau'];
        if($key == 'billingaddress') { $addr = $order->getBillingAddress(); }
        else if($key == 'shippingaddress') { $addr = $order->getShipmentAddress(); }

        $addrC = 0;
        foreach($order->getAddressKeys() as $addrKey) {
            $addrVals[$addrC] = [];
            $addrText = (($addr->where('name','=', $key.'_'.$addrKey)->first())
            ? $addr->where('name','=', $key.'_'.$addrKey)->first()->value : '-' );

            if(in_array($addrKey, $addrWithTitle)) { $addrVals[$addrC]['title'] = $order->getTitleForAttributeKey($addrKey); }
            if(in_array($addrKey, $addrSameRow)) { $addrVals[$addrC - 1]['text'] =  $addrVals[$addrC - 1]['text'] .' '. $addrText; continue; }
            if($addrKey == 'gender' && isset($genderVals[$addrText])) { $addrText = $genderVals[$addrText]; }
            if($addrKey == 'tel' || $addrKey == 'phone_number')
            { $addrVals[$addrC]['margin'] = true; }
            $addrVals[$addrC]['text'] = $addrText;
            $addrC++;
        }
        return $addrVals;
    }

    private function getBillingAddr(Order $order) {
        $shad = 'billingaddress_';
        $shipment = $order->getBillingAddress();
        return [
            'organisation_name' => ($shipment->where('name', '=', $shad.'organisation_name')->first() ? $shipment->where('name', '=', $shad.'organisation_name')->first()->value : ''),
            'name' => ($shipment->where('name', '=', $shad.'vorname')->first() ? $shipment->where('name', '=', $shad.'vorname')->first()->value : '').' '.($shipment->where('name', '=', $shad.'nachname')->first() ? $shipment->where('name', '=', $shad.'nachname')->first()->value : ''),
            'street' => ($shipment->where('name', '=', $shad.'street')->first() ? $shipment->where('name', '=', $shad.'street')->first()->value : ''),
            'city' => ($shipment->where('name', '=', $shad.'postcode')->first() ? $shipment->where('name', '=', $shad.'postcode')->first()->value : '').' '.($shipment->where('name', '=', $shad.'city')->first() ? $shipment->where('name', '=', $shad.'city')->first()->value : ''),
            'tel' => ($shipment->where('name', '=', $shad.'phone_number')->first() ? $shipment->where('name', '=', $shad.'phone_number')->first()->value : ''),
            'phone_number' => ($shipment->where('name', '=', $shad.'phone_number')->first() ? $shipment->where('name', '=', $shad.'phone_number')->first()->value : ''),
        ];
    }

    private function getShipmentAddr(Order $order) {
        $shad = 'shippingaddress_';
        $shipment = $order->getShipmentAddress();
        return [
            'name' => ($shipment->where('name', '=', $shad.'vorname')->first() ? $shipment->where('name', '=', $shad.'vorname')->first()->value : '').' '.($shipment->where('name', '=', $shad.'nachname')->first() ? $shipment->where('name', '=', $shad.'nachname')->first()->value : ''),
            'street' => ($shipment->where('name', '=', $shad.'street')->first() ? $shipment->where('name', '=', $shad.'street')->first()->value : ''),
            'city' => ($shipment->where('name', '=', $shad.'postcode')->first() ? $shipment->where('name', '=', $shad.'postcode')->first()->value : '').' '.($shipment->where('name', '=', $shad.'city')->first() ? $shipment->where('name', '=', $shad.'city')->first()->value : ''),
        ];
    }

    private function getCompanyAddr() {
        $settings = Setting::first();
        return [
            'ust_id' =>  ($settings->attributes()->where('name', '=', 'ust_id')->first() ? $settings->attributes()->where('name', '=', 'ust_id')->first()->value : ''),
            'firm' =>  ($settings->attributes()->where('name', '=', 'brandname')->first() ? $settings->attributes()->where('name', '=', 'brandname')->first()->value : ''),
            'name' => ($settings->attributes()->where('name', '=', 'company')->first() ? $settings->attributes()->where('name', '=', 'company')->first()->value : ''),
            'street' => ($settings->attributes()->where('name', '=', 'street')->first() ? $settings->attributes()->where('name', '=', 'street')->first()->value : ''),
            'city' => ($settings->attributes()->where('name', '=', 'postcode')->first() ? $settings->attributes()->where('name', '=', 'postcode')->first()->value : '')
            .' '.($settings->attributes()->where('name', '=', 'city')->first() ? $settings->attributes()->where('name', '=', 'city')->first()->value : ''),
            'tel' => ($settings->attributes()->where('name', '=', 'tel')->first() ? $settings->attributes()->where('name', '=', 'tel')->first()->value : ''),
            'email' => ($settings->attributes()->where('name', '=', 'email')->first() ? $settings->attributes()->where('name', '=', 'email')->first()->value : ''),
            'web' => ($settings->attributes()->where('name', '=', 'domain')->first() ? $settings->attributes()->where('name', '=', 'domain')->first()->value : '')
        ];
    }

    public function getCustomerOrders() {

    }

    public function getCustomerOrder() {

    }

    public function postOrder(Request $request)
    {
        $invoiceAddressMap = [
            'organisation_name' => [ 'organisation_name' ],
            'vorname' => [ 'first_name', 'vorname' ],
            'nachname' => [ 'last_name', 'nachname' ],
            'gender' => [ 'gender' ],
            'street' => [ 'street' ],
            'postcode','city','region','tel','email','phone_number'];
        $data = $request->data;
        $payment_key = $data['payment_key'];
        $shipment_key = $data['shipment_key'];
        $order_status= $data['order_status'];
        $registered_email = $data['registered_email'];
        $invoice_address = $data['invoice_address'];
        $shipping_address = $data['shipping_address'];
        $shipment_price = $data['shipment_price'];
        $voucher_gesamt = $data['voucher_gesamt'];
        $positions = $data['positions'];
        $Freitext = (isset($data['text'])?$data['text']:"");
        $payment = Config_Payment::where('payment_key', '=', $payment_key)->first();
        $shipment = Config_Shipment::where('shipment_key', '=', $shipment_key)->first();
        $status = 1;
        $BestandsVeraendernd=true;
        switch($payment_key)
        {   case "Vorkasse": $status = 2; break;
            case "PayPal": $status = 3; break;
            case "Bezahlung auf Rechnung": $status = 2; break;
            case "Zahlung im Geschäft": $status = 3; $BestandsVeraendernd=false; break;
        }

        $invoice_addr = json_decode($invoice_address);
        $thisCustomerMail = "";
        if(property_exists($invoice_addr, 'email') && $invoice_addr->email != null)
        { $thisCustomerMail = $invoice_addr->email; }

        $orderCount = Order::count();
        $order = Order::create([
            'fk_config_payment_id' => (($payment) ? $payment->id : null),
            'fk_config_shipment_id' => ($shipment) ? $shipment->id : null,
            'fk_order_status_id' => ((!$BestandsVeraendernd)? 10 : (($status) ? $status : null) ),
            'fk_provider_id' => $request->attributes->get('provider')->id,
            'shipment_price' => $shipment_price,
            'voucher_price' => $voucher_gesamt,
            'number' => ++$orderCount,
            'email' => $thisCustomerMail,
            'text' => $Freitext
        ]);



        foreach(json_decode($positions) as $position)
        {   $variation = false;
            if(isset($position->variation) && isset($position->variation->vstcl_identifier))
            {   $variation = Article_Variation::where('vstcl_identifier', '=', $position->variation->vstcl_identifier)->first();}
            if(!$variation && isset($position->article) && isset($position->article->vstcl_identifier))
            {   $variation = Article_Variation::where('vstcl_identifier', '=', $position->article->vstcl_identifier)->first();}

            $orderArticle = OrderArticle::create([
                'fk_article_id' => $variation->article()->first()->id,
                'fk_article_variation_id' => $variation->id,
                'fk_order_id' => $order->id,
                'fk_orderarticle_status_id' => 1,
                'quantity' => $position->quantity,
                'tax' => \App\Helpers\VAT::getVAT(),
                'price' => ($position->price / $position->quantity ) * 100,
            ]);
            //Log::info($position->attributes);
            if(isset($position->attributes))
            {   foreach($position->attributes as $attr)
                {   $orderArticle = OrderArticle_Attribute::create(['fk_orderarticle_id' => $orderArticle->id,'name' => $attr->name,'value' => $attr->value ]); }
            }

            if($BestandsVeraendernd)
            {
                //*/ Export Fee Umsatzdatei Add Line
                $org_ean = $variation->getEan();
                $price = ($position->price / $position->quantity);
                $OrderNumber = "AN-".str_pad($order->number, 6, '0', STR_PAD_LEFT);
                $OrderNumberType = Setting::where('fk_settings_type_id','=','1')->first()->attributes()->where('name', '=', 'number_order')->first();
                if($OrderNumberType && $OrderNumberType!=null)
                { $OrderNumber = "AN-".str_pad($order->number, strlen((string) $OrderNumberType->value), '0', STR_PAD_LEFT); }
                $FeeOnlineBranch = Settings_Attribute::where('name','=','fee_online_branch_id')->first();
                if($FeeOnlineBranch){$FeeOnlineBranch=$FeeOnlineBranch->value;}
                $VerkaufsFilialie = $FeeOnlineBranch;
                $UrsprungsFilialie_id = (($variation->getFirstBranchWithStock())? $variation->getFirstBranchWithStock()->fk_branch_id : "");
                $branch = Branch::where('id', '=', $UrsprungsFilialie_id)->first();
                $UrsprungsFilialie="";
                if($branch){$UrsprungsFilialie=$branch->wawi_number;}

                $zaC = new ZalandoController();
                $zaC->fee_export($variation, $OrderNumber, 'V',$VerkaufsFilialie,$UrsprungsFilialie,$org_ean, $price, date("Ymd"), date("H:i:s"),'vsshop');
                //*/
            }

        }

        foreach($invoiceAddressMap as $addressKey => $addressVal) {
            $val = ''; $name = ''; $key = '';
            if(is_array($addressVal)) {
                $name = $addressKey;
                if(property_exists($invoice_addr, $addressKey))
                { $key = $addressKey; }
                else
                {   foreach($addressVal as $aKey) {
                        if(property_exists($invoice_addr, $aKey))
                        { $key = $aKey; }
                    }
                }
            }
            else { $name = $addressVal; $key = $addressVal; }

            $val = ($invoice_addr->{$key}) ?? '';
            $orderAttr = Order_Attribute::updateOrCreate(
            [ 'fk_order_id' => $order->id, 'name' => 'billingaddress_'.$name ],
            [ 'value' => $val ] );
        }
        if($shipping_address != null) {
            $shipping_addr = json_decode($shipping_address);
            foreach($invoiceAddressMap as $addressKey => $addressVal) {
                $val = '';$name = ''; $key = '';
                if(is_array($addressVal)) {
                    $name = $addressKey;
                    if(property_exists($shipping_addr, $addressKey)) {
                        $key = $addressKey;
                    }
                    else {
                        foreach($addressVal as $aKey) {
                            if(property_exists($shipping_addr, $aKey)) {
                                $key = $aKey;
                            }
                        }
                    }
                }
                else { $name = $addressVal;$key = $addressVal;}
                $val = ($shipping_addr->{$key}) ?? '';
                $orderAttr = Order_Attribute::updateOrCreate(
                    [ 'fk_order_id' => $order->id, 'name' => 'shippingaddress_'.$name ],
                    [ 'value' => $val ]
                );
            }
        }
        else {
            $orderAttr = Order_Attribute::updateOrCreate(
                [ 'fk_order_id' => $order->id,'name' => 'billingasshipping' ],
                [ 'value' => 'true' ]
            );
        }

        $voucher_data = json_decode($data['voucher_data']);
        foreach($voucher_data as $VoucherID => $Voucher)
        {
            $orderVoucher = OrderVoucher::create(
                [
                    'fk_order_id' => $order->id
                    ,'fk_voucher_id' => $VoucherID
                    ,'code' => $Voucher->code
                    ,'for' => $Voucher->for
                    ,'type' => $Voucher->type
                    ,'value' => $Voucher->value
                    ,'cart_price_limit' => $Voucher->cart_price_limit
                    ,'cart_price_min' => $Voucher->cart_price_min
                    ,'unique_useable' => $Voucher->unique_useable
                ]
            );
        }

        if(property_exists($invoice_addr, 'email') && $invoice_addr->email != null) {
            try {
                $thisCC = 'schmoock@visc-media.de'; $thisCCs = getenv('SHOP_MAIL_CC','schmoock@visc-media.de');

				$thisApiKey = $request->apikey;
				$thisTenant = Tenant_Keys::where('access_key', $thisApiKey)->first()->tenant()->get()->first();
                $Tenant_type = $thisTenant->type()->first()->type_key;
                //Log::debug("Tenant subdomain: ".$thisTenant." key=".$request->apikey);
                if($thisCCs != ""){$thisCCs = eval("return " . $thisCCs . ";");}
                if(is_array($thisCCs))
                {
                    foreach($thisCCs as $CC_tenant_subdomain => $CC_Mail)
                    { if($thisTenant->subdomain == $CC_tenant_subdomain) { $thisCC = $CC_Mail; } }
                }

                if($Tenant_type=='vstcl-industry')
                {
                    $newAB_Nummer = Order_Attribute::where('name','=','AB_Nr')->count();
                    if(!$newAB_Nummer){$newAB_Nummer = 0;}else{$newAB_Nummer = $newAB_Nummer;} $newAB_Nummer++;
                    $AB_Nummer = Setting::getReceiptNameWithNumberByKey('order_confirmation', $newAB_Nummer);
                    $orderAttr = Order_Attribute::updateOrCreate([ 'fk_order_id' => $order->id, 'name' => 'AB_Nr' ],[ 'value' => $AB_Nummer ]);

                    $pdfPath = storage_path()."/customers/".$thisTenant->subdomain."/pdf/";

                    $PDF = $this->pdfBestellbestaetigung($order->id, $AB_Nummer, $thisTenant->subdomain);
                    $bestellData = $this->GetBestellData_IND($order->id, $AB_Nummer);
                    $PDF->save($pdfPath.$AB_Nummer.'.pdf');
                    Mail::to($invoice_addr->email)->bcc($thisCC, 'Shop System')
                    ->send(new OrderConfirmed_industry($order,$pdfPath.$AB_Nummer.'.pdf', $AB_Nummer.'.pdf',$bestellData));
                }
                else
                {Mail::to($invoice_addr->email)->bcc($thisCC, 'Shop System')->send(new OrderConfirmed($order));}

            }
            catch(Exception $e) {

            }
        }

        $response = [
            'success' => 1,
            'vstcl_order_id' => $order->id
        ];
        if($payment_key == 'PayPal') {
            $invoiceCount = Invoice::count();
            $invoice = Invoice::create([
                'fk_order_id' => $order->id,
                'fk_invoice_status_id' => 1,
                'number' => ++$invoiceCount
            ]);
            $response['invoice_number'] = Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number);
        }

        return response()->json($response, 200);

    }

    public function GetBestellData_IND($orderid, $AB_Nummer)
    {   $orderTax = \App\Helpers\VAT::getVAT();
        $netto_gesamt=0;$tax_gesamt=0;$summe_gesamt=0;
        $order = Order::where('id', $orderid)->get()->first();
        $companyAddr = $this->getCompanyAddr();
        $billingAddr = $this->getBillingAddr($order);

        $data = [
            'positions' => [
                'columns' => ['Pos.','Nummer','Beschreibung','Menge','Preis','MwSt.','Betrag'],
                'rows' => []
            ],
            'positions_mail' => [
                'columns' => ['Artikel-Nr','Artikelbezeichnung','Menge','Einzelpreis €','Summe €'],
                'rows' => []
            ]
        ];
        $articles = $order->articles()->get();
        $count = 0; $tax_gesamt = 0; $netto_gesamt = 0; $summe_gesamt = 0;
        foreach($articles as $article) {
            $count++;
            $mainArticle = $article->article()->first();
            $varArticle = $article->variation()->first();
            if($mainArticle == null || $varArticle == null) { continue; }

            $Beschreibung = $mainArticle->name."";
            foreach ($mainArticle->attributes as $attribute)
            {   if($attribute->group() && $attribute->group()->first() && $attribute->group()->first()->active == 1 && $attribute->group()->first()->name != "VPE")
                {   $Beschreibung .=  '<br><span style="font-size:13px;padding:0;margin:0;">'.$attribute->name.': '.$attribute->value.'</span>';}
            }
            $Beschreibung .= "";
            $netto_gesamt += $article->price * $article->quantity;

            $tax_gesamt += ( ($article->price * $article->tax) / 100) * $article->quantity; $orderTax=$article->tax;
            $summe_gesamt += ( ( ($article->price * $article->tax) / 100)+($article->price) ) * $article->quantity;
            $data['positions']['rows'][] = [
                'values' => [ $count
                    ,$mainArticle->number
                    ,$Beschreibung
                    ,$article->quantity
                    ,Article::getFormattedPrice($article->price / 100)
                    ,Article::getFormattedPrice( ( ( ($article->price * $article->tax) / 100) / 100) * $article->quantity )
                    ,Article::getFormattedPrice( ( ( ( ($article->price * $article->tax) / 100)+($article->price)) / 100) * $article->quantity )
                ]
            ];
            $data['positions_mail']['rows'][] = [
                'values' => [ $mainArticle->number
                    ,$mainArticle->name
                    ,$article->quantity
                    ,Article::getFormattedPrice( ($article->price / 100)  )
                    ,Article::getFormattedPrice( ( ( ( ($article->price * $article->tax) / 100)+($article->price)) / 100) * $article->quantity )
                ]
            ];
        }
        $data['orderTax']=$orderTax;
        $data['netto_gesamt']=Article::getFormattedPrice($netto_gesamt/100);
        $data['summe_gesamt']=Article::getFormattedPrice($summe_gesamt/100);
        $data['tax_gesamt_netto']= Article::getFormattedPrice(
            (($tax_gesamt/100))
        );
        $data['tax_gesamt']= Article::getFormattedPrice(
            (($tax_gesamt/100)
            +((($order->getShipmentPrice()-$order->getOrderVoucherPrice()) / 100 ) * \App\Helpers\VAT::getVAT() )
            )
        );
        //Article::getFormattedPrice($tax_gesamt/100);
        $data['total_gesamt']=Article::getFormattedPrice(
            ($summe_gesamt/100)
            +$order->getShipmentPrice()-$order->getOrderVoucherPrice()
            +((($order->getShipmentPrice()-$order->getOrderVoucherPrice()) / 100 ) * \App\Helpers\VAT::getVAT() )
        );

        return $data;
    }

    public function testMail() {
        $order = Order::find(1);
        Mail::to("schmoock@visc-media.de")->send(new OrderConfirmed($order));
    }

    public function updateOrder() {

    }

    public function updatePayPalPayment(Request $request) {
        $vstcl_order_id = $request->vstcl_order_id;
        $paypal_status = $request->paypal_status;
        $order = Order::find($vstcl_order_id);

        if($paypal_status == 'payed') {
            $Tenant_type = config()->get('tenant.tenant_type');
            if($Tenant_type=='vstcl-industry')
            { $totalPrice = ($order->getFullPrice_ind() / 100 ); }
            else
            { $totalPrice = ($order->getFullPrice() / 100 ); }
            Payment::create([
                'fk_order_id' => $order->id,
                'fk_config_payment_id' => 1,
                'payment_date' => date('Y-m-d'),
                'payment_amount' => $totalPrice
            ]);

            $order->fk_order_status_id = 3;

        }
        else if($paypal_status == 'cancelled')
        { $order->fk_order_status_id = 6; }
        else { $order->fk_order_status_id = 1; }

        $order->save();

        return response()->json([
            'success' => 1
        ], 200);
    }

    private function dataTablesOrderData($selection = null, $columnconfig = 'orders') {
        $response = datatables()->of(
            (($selection) ? $selection
            : Order::select(
                'orders.id','orders.created_at','orders.updated_at','fk_provider_id','fk_order_status_id','fk_config_shipment_id','fk_config_payment_id','shipment_price'
                ,'number','voucher_price','za_number','email','text'
            )->with(['status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ]))
        )
        ->addColumn('za_store_id', function(Order $order) {
            $za_store_id = $order->attributes()->where('name', '=', 'za_store_id')->first();
            if($za_store_id) { return $za_store_id->value; } return "";
        })
        ->addColumn('action', 'action_button')
        ->addColumn('number', function(Order $order) {
            return Setting::getReceiptNameWithNumberByKey('order', $order->number);
        })
        ->rawColumns(['action', 'action_button','number'])
        ->addIndexColumn()
        ->make(true);

        $data = $response->getData(true);
        $data['columnconfig'] = Auth::user()->getTableColumnConfig($columnconfig);

        return json_encode($data);
    }


    public function indexOrderConfirmations() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with([ 'status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ])
            ->where(function ($query) {
                $query->whereHas('attributes',function ($query) {
                    $query->where('name', '=', 'AB_Nr');
                });
            })
            ,'order_confirmations');
        }
        return view('tenant.modules.order.index.order_confirmations', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexDeliveryNotes() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(
                Order::select('*')->with(['status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ])
                ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('completed')]),
                'delivery_notes'
            );
        }
        return view('tenant.modules.order.index.delivery_notes', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexInvoices() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with([ 'status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('completed')]), 'invoices');
        } return view('tenant.modules.order.index.invoices', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexRetours() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with(['status', 'attributes', 'provider', 'provider.type',  'payment',  'shipment' ])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('completed')]), 'retours');
        } return view('tenant.modules.order.index.retours', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexCreditNotes() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with(['status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment'])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('refunded')]), 'credit_notes');
        } return view('tenant.modules.order.index.credit_notes', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexOpos() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with(['status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('open'), Order_Status::getIdByKey('awaiting_payment')]), 'opos');
        } return view('tenant.modules.order.index.opos', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexConflicts() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with([ 'status',  'attributes',  'provider',  'provider.type',  'payment',  'shipment' ])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('open')]), 'conflicts');
        } return view('tenant.modules.order.index.conflicts', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexPartialShipments() {
        if(request()->ajax()) {
            return $this->dataTablesOrderData(Order::select('*')->with(['status', 'attributes', 'provider', 'provider.type', 'payment', 'shipment' ])
            ->whereIn('fk_order_status_id' , [Order_Status::getIdByKey('partially_shipped')]), 'partial_shipments');
        } return view('tenant.modules.order.index.partial_shipments', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    public function indexStatistics() {
        if(request()->ajax()) { return $this->dataTablesOrderData(null, 'statistics'); }
        return view('tenant.modules.order.index.statistics', ['sideNavConfig' => Order::sidenavConfig()]);
    }
}
