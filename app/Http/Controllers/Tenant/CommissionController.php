<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

use App\Tenant\Commission;
use App\Tenant\CommissionOrder_Status;
use App\Tenant\Order, App\Tenant\Order_Status;
use App\Tenant\Setting;
use App\Tenant\Invoice, App\Tenant\Invoice_Status;
use App\Tenant\OrderArticle_Status;
use App\Http\Controllers\Tenant\OrderController;
use Illuminate\Http\Request;
use Response;
use PDF;
use PdfMerger; use Log;

class CommissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request()->ajax()) {
            return datatables()->of(Commission::select('*')->with(['orders']))
            ->addColumn('number', function(Commission $comm) {
                return Setting::getReceiptNameWithNumberByKey('commission', $comm->number);
            })
            ->addColumn('orderCount', function(Commission $comm) {
                return $comm->orders()->count();
            })
            ->addColumn('articleCount', function(Commission $comm) {
                $orders = $comm->orders()->get();
                $articleCount = 0;
                foreach($orders as $order) {
                    $articleCount += $order->articles()->count();
                }
                return $articleCount;
            })
            ->addColumn('status', function(Commission $comm){
                $commOrders = $comm->orders()->get();
                $ordersCompleted = [];
                foreach($commOrders as $commOrder) {
                    $status = CommissionOrder_Status::find($commOrder->pivot->fk_commissionorder_status_id);
                    if($status->description == 'Abgeschlossen') {
                        $ordersCompleted[] = $commOrder->fk_order_id;
                    }
                }
                return (count($ordersCompleted) == count($commOrders)) ? 'Abgeschlossen' : 'In Bearbeitung';
            })
            ->addColumn('fortschritt', function(Commission $comm) {
                $commOrders = $comm->orders()->get();
                $ordersCompleted = [];
                foreach($commOrders as $commOrder) {
                    $status = CommissionOrder_Status::find($commOrder->pivot->fk_commissionorder_status_id);
                    if($status->description == 'Abgeschlossen') {
                        $ordersCompleted[] = $commOrder->fk_order_id;
                    }
                }
                return count($ordersCompleted).'/'.count($commOrders);
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }

        return view('tenant.modules.order.index.commission', ['sideNavConfig' => Order::sidenavConfig()]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $configArray = [];
        return view('tenant.modules.order.create.commission', [
            'configArray' => $configArray,
            'commissionId' => '0',
            'sideNavConfig' => Order::sidenavConfig()]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Commission  $commission
     * @return \Illuminate\Http\Response
     */
    public function show(Commission $commission)
    {
        //
    }

    public function showOrder($commId, $orderId) {
        $comm = Commission::find($commId);
        $order = $comm->orders()->where('fk_order_id','=',$orderId)->get()->first();

        $configArray = [];

        return view('tenant.modules.order.show.commissionorder', [
            'commission' => $comm,
            'configArray' => $configArray,
            'commissionId' => $comm->id,
            'orderId' => $orderId,
            'commNumber' => Setting::getReceiptNameWithNumberByKey('commission', $comm->number),
            'orderNumber' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
            'sideNavConfig' => Order::sidenavConfig()
            ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Commission  $commission
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $commission = Commission::where('id', '=', $id)->get()->first();
        $configArray = [];
        return view('tenant.modules.order.edit.commission', [
            'configArray' => $configArray,
            'commissionId' => $commission->id,
            'commNumber' => Setting::getReceiptNameWithNumberByKey('commission', $commission->number),
            'commission' => $commission,
            'sideNavConfig' => Order::sidenavConfig()]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Commission  $commission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id = null)
    {
        $selectedOrders = $request->selectedRow;
        $products = [];
        $pdfPath = storage_path()."/customers/".$request->attributes->get('identifier')."/pdf/";
        $orderController = new OrderController();
        if($id == 'undefined') {
            $commissionC = Commission::count();
            $commission = Commission::create([
                'sysnumber' => (Commission::latest('id')->first()) ? Commission::latest('id')->first()->id + 1 : 1,
                'number' => ++$commissionC
            ]);
        }
        else {
            $commission = Commission::find($id);
        }

        $commOrders = [];
        $response = [
            'commission_id' => $commission->id,
        ];
        $packscheinUrls = [];

        foreach($selectedOrders as $selectedOrder) {
            $order = Order::where('id', '=', $selectedOrder)->first();
            if(!$order) { continue; }
            $commOrders[$order->id] = ['fk_commissionorder_status_id' => 1];
            $orderProducts = $order->articles()->where('fk_orderarticle_status_id', '=', '1');

            $packscheinName = Setting::getReceiptNameWithNumberByKey('packing', $order->number).'.pdf';

            if(!file_exists($pdfPath.$packscheinName)) {
                $packschein = $orderController->pdfPackschein($order->id);
                $packschein->save($pdfPath.$packscheinName);
            }

            $packscheinUrls[] = $pdfPath.$packscheinName;
            if(!$orderProducts->first()) {
                continue;
            }
            foreach($orderProducts as $orderProduct) {

            }
        }
        $commission->orders()->sync($commOrders);
        $commissionPdfName = Setting::getReceiptNameWithNumberByKey('commission', $commission->number).'.pdf';
        if(!file_exists($pdfPath.$commissionPdfName)) {
            $commissionPdf = $this->getPDF($commission->id,$request->attributes->get('identifier'));
            $commissionPdf->save($pdfPath.$commissionPdfName);
            $commissionPdf->download($pdfPath.$commissionPdfName);
        }

        /*
        //TODO: Generate Merged PDF
        if(!file_exists($pdfPath.'Full-'.$commissionPdfName)) {
            $fullPdf = new \LynX39\LaraPdfMerger\PdfManage;
            $fullPdf->addPdf($pdfPath.$commissionPdfName, 'all');
            foreach($packscheinUrls as $packscheinUrl) {
                $fullPdf->addPdf($packscheinUrl, 'all');
            }
            $fullPdf->merge('file', $pdfPath.'Full-'.$commissionPdfName, 'P');
        }*/

        return Response::json($response);
    }

    public function downloadPDF(Request $request, $id) {
        $commission = Commission::find($id);
        $pdfPath = storage_path()."/customers/".$request->attributes->get('identifier')."/pdf/";
        $commissionPdfName = Setting::getReceiptNameWithNumberByKey('commission', $commission->number).'.pdf';
        if(!file_exists($pdfPath.$commissionPdfName)) {
            $commissionPdf = $this->getPDF($commission->id,$request->attributes->get('identifier'));
            $commissionPdf->save($pdfPath.$commissionPdfName);
        }
        return response()->download($pdfPath.$commissionPdfName);
    }

    public function downloadPacking(Request $request, $commId, $orderId) {
        $orderController = new OrderController();
        $order = Order::find($orderId);
        $pdfPath = storage_path()."/customers/".$request->attributes->get('identifier')."/pdf/";
        $packingPdfName = Setting::getReceiptNameWithNumberByKey('packing', $order->number).'.pdf';
        if(!file_exists($pdfPath.$packingPdfName)) {
            $packschein = $orderController->pdfPackschein($orderId);
            $packschein->save($pdfPath.$packingPdfName);
        }
        return response()->download($pdfPath.$packingPdfName);
    }

    public function completeOrder(Request $request, $commId, $orderId, $next = false) {
        $orderController = new OrderController();
        $commission = Commission::find($commId);
        $pdfPath = storage_path()."/customers/".$request->attributes->get('identifier')."/pdf/";
        $fulfilled = true;
        $order = Order::find($orderId);
        $commOrder = $commission->orders()->where('fk_order_id', '=', $orderId)->first();
        $status = CommissionOrder_Status::find($commOrder->pivot->fk_commissionorder_status_id);

        foreach($order->articles()->get() as $article) {
            if($article->status()->first()->description != 'Versendet') {
                $fulfilled = false;
            }
        }
        if(!$fulfilled) {
            return redirect()->back()->withError('Die Bestellung kann noch nicht abgeschlossen werden.');
        }
        else {
            $invoice = Invoice::where('fk_order_id', '=', $orderId)->first();
            if(!$invoice || ($invoice && !file_exists($pdfPath.Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number).'.pdf')) ) {
                if(!$invoice) {
                    $invoiceC = Invoice::count();
                    $invoice = Invoice::create([
                        'fk_order_id' => $orderId,
                        'fk_invoice_status_id' => Invoice_Status::where('description', '=', 'Offen')->first()->id,
                        'number' => ++$invoiceC
                    ]);
                }
                $invoicePdf = $orderController->pdfRechnung($orderId, $invoice);
                $invoicePdf->save($pdfPath.Setting::getReceiptNameWithNumberByKey('invoice', $invoice->number).'.pdf');
            }
            if(!file_exists($pdfPath.Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number).'.pdf')) {
                $delivery_notePdf = $orderController->pdfLieferschein($orderId);
                $delivery_notePdf->save($pdfPath.Setting::getReceiptNameWithNumberByKey('delivery_note', $order->number).'.pdf');
            }
            if(!file_exists($pdfPath.'retoureschein-'.$order->number.'.pdf')) {
                $retourePdf = $orderController->pdfRetoure($orderId);
                $retourePdf->save($pdfPath.'retoureschein-'.$order->number.'.pdf');
            }

            $commOrder->pivot->fk_commissionorder_status_id = CommissionOrder_Status::where('description','=','Abgeschlossen')->first()->id;
            $commOrder->pivot->save();
            $order->fk_order_status_id = Order_Status::where('key','=','completed')->first()->id;
            $order->save();
            return redirect()
                ->route('tenant.commissions.edit', [$request->attributes->get('identifier'), $commId])
                ->withSuccess('Bestellung erfolgreich abgeschlossen.');
        }
    }

    public function updateOrder(Request $request, $commId, $orderId) {
        if(!$request->exists('selectedRow')) {
            return redirect()->back()->withWarning('Bitte wählen Sie mindestens eine Position');
        }
        if(!$request->exists('packing')) {
            return redirect()->back()->withWarning('Übermitteln Sie mindestens eine Mengenangabe');
        }
        $articleIds = $request->selectedRow;
        $packings = $request->packing;

        $orderController = new OrderController();
        $commission = Commission::find($commId);
        $order = Order::find($orderId);

        foreach($articleIds as $articleId) {
            foreach($packings as $packingId => $articlePacking) {
                foreach($articlePacking as $pArticleId => $quantity) {
                    if($pArticleId == $articleId && $quantity != '') {
                        $orderArticle = $order->articles()->where('id', '=', $articleId)->first();
                        $orderArticle->packed = ($orderArticle->packed == null) ? (int)$quantity : (int)$orderArticle->packed + (int)$quantity;

                        if((int)$orderArticle->packed >= (int)$orderArticle->quantity) {
                            $orderArticle->fk_orderarticle_status_id = OrderArticle_Status::where('description', '=', 'Versendet')->first()->id;
                        }
                        else if((int)$orderArticle->packed != null && (int)$orderArticle->packed > 0 && (int)$orderArticle->packed < (int)$orderArticle->quantity) {
                            $orderArticle->fk_orderarticle_status_id = OrderArticle_Status::where('description', '=', 'Kein Bestand')->first()->id;
                        }
                        else {
                            $orderArticle->fk_orderarticle_status_id = OrderArticle_Status::where('description', '=', 'In Vorbereitung')->first()->id;
                        }
                        $orderArticle->save();
                    }
                }
            }

        }

        return redirect()->back()->withSuccess('Packliste erfolgreich gespeichert');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Commission  $commission
     * @return \Illuminate\Http\Response
     */
    public function destroy(Commission $commission)
    {
        //
    }

    public function loadOrders() {
        $statuses = [3];
        $orders = Order::whereIn('fk_order_status_id', $statuses)
        ->with([
            'status',
            'attributes',
            'provider',
            'provider.type',
            'payment',
            'shipment',
        ])
        ->whereDoesntHave('commissions')
        ->get();
        if(request()->ajax()) {
            return datatables()->of($orders)
            ->addColumn('number', function(Order $order) {
                return Setting::getReceiptNameWithNumberByKey('order', $order->number);
            })
            ->addColumn('action', 'action_button')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function loadPackscheine($id) {
        $comm = Commission::where('id', '=', $id)->get()->first();
        $orderIds = $comm->orders()->pluck('fk_order_id');
        $rows = [];
        foreach($orderIds as $orderId) {
            $order = Order::find($orderId);
            $commOrder = $comm->orders()->where('fk_order_id', '=', $orderId)->first();
            $status = CommissionOrder_Status::find($commOrder->pivot->fk_commissionorder_status_id);
            $packzettel = $order->documents()->with([
                'documents.attributes',
                'documents.type' => function($query) {
                    $query->where('name', '=', 'packzettel');
                }
            ])->get();
            $articleCount = $order->articles()->sum('quantity');
            $articlePacked = $order->articles()->sum('packed');
            $avg = ((int)$articleCount + (($articlePacked == null) ? 0 : (int)$articlePacked)) / 2;
            $rows[] = [
                'id' => $orderId,
                'orderId' => $orderId,
                'pack_number' => Setting::getReceiptNameWithNumberByKey('packing', $order->number),
                'order_number' => Setting::getReceiptNameWithNumberByKey('order', $order->number),
                'article_count' => $articleCount,
                'fortschritt' => ($avg == 0) ? 0 : ((int)$articleCount - (($articlePacked == null) ? 0 : (int)$articlePacked)),
                'status' => $status->description
            ];
        }

        return datatables()->of(collect($rows))
        ->addColumn('action', 'action_button')
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);
    }

    public function loadArticlesForOrder($commId, $orderId) {
        $comm = Commission::where('id', '=', $commId)->get()->first();
        $commOrder = $comm->orders()->where('fk_order_id', '=', $orderId)->first();
        $order = Order::find($orderId);
        $articles = $order->articles();
        $rows = [];

        foreach($articles->get() as $article) {
            if($article->fk_article_variation_id == null) {
                $mainArticle = $article->article()->first();
                $articleVar = $mainArticle->variations()->first();
            }
            else {
                $articleVar = $article->variation()->first();
                $mainArticle = $articleVar->article()->first();
            }
            if(!$articleVar) {
                continue;
            }

            $varBranch = $articleVar->branches()->first();

            $rows[] = [
                'id' => $article->id,
                'img' => ($articleVar->getThumbnailBigImg()->first()) ? $articleVar->getThumbnailBigImg()->first()->location : '',
                'color' => $articleVar->getColorText(),
                'size' => $articleVar->getSizeText(),
                'length' => $articleVar->getLengthText(),
                'producer' => $mainArticle->getProducerName(),
                'number' => $mainArticle->number,
                'ean' => $articleVar->getEan(),
                'filiale' => ($varBranch) ? $varBranch->branch()->first()->name : '-',
                'quantity' => (int)$article->quantity - (($article->packed) ? $article->packed : 0)
            ];
        }
        //dd($rows);

        return datatables()->of(collect($rows))
        ->addColumn('action', 'action_button')
        ->rawColumns(['action'])
        ->addIndexColumn()
        ->make(true);
    }

    public function getPDF($id,$customer=false){
        $commission = Commission::where('id', '=', $id)->first();

        $data = [
            'positions' => [
                'columns' => ['Lager', 'Platz', 'Bezeichnung', 'LFR-Nr.', 'Lieferant', 'Menge', 'EAN', 'Erl.'],
                'rows' => []
            ]
        ];
        // Overrides
        
        $Tenant = config()->get('tenant.identifier'); $check1=false;
        if($customer){$Tenant = $customer;}
        switch($Tenant)
        {
            case "olgasmodewelt": 
                $check1=true;
                $data = [
                    'positions' => [
                        'columns' => ['Lager', 'Platz', 'Bezeichnung', 'LFR-Nr.', 'Lieferant', 'Menge', 'Artikel-Nr', 'Erl.'],
                        'rows' => []
                    ]
                ];
            break;
        }

        
        $orderIds = $commission->orders()->pluck('fk_order_id');
        $articles = [];
        foreach($orderIds as $orderId) {
            $order = Order::find($orderId);
            $oArticles = $order->articles()->get();
            foreach($oArticles as $oArticle) {
                $mainArticle = $oArticle->article()->first();
                $varArticle = $oArticle->variation()->first();
                if($mainArticle == null || $varArticle == null) {
                    continue;
                }
                if(isset($articles[$varArticle->getEan()])) {
                    $articles[$varArticle->getEan()]['quantity'] += $oArticle->quantity;
                }
                else {
                    $articles[$varArticle->getEan()] = [
                        'lager' => '',
                        'platz' => '',
                        'description' => $mainArticle->name,
                        'lfrtnr' => $mainArticle->getAttrByName('hersteller-nr'),
                        'lfrt' => $mainArticle->getAttrByName('hersteller'),
                        'quantity' => $oArticle->quantity,
                        'number' => (($mainArticle->number != null) ? $mainArticle->number : '-')
                    ];
                }
            }
        } 
        foreach($articles as $articleEan => $article) {
            $data['positions']['rows'][] = [
                'values' => [
                    $article['lager'],
                    $article['platz'],
                    $article['description'],
                    $article['lfrtnr'],
                    $article['lfrt'],
                    $article['quantity'],                    
                    (($check1)? $article['number'] : $articleEan),
                    '<input type="checkbox">'
                ]
            ];
        }

        $pdf = PDF::loadView('tenant.pdf.kommission', [
            'data' => $data,
            'number' => Setting::getReceiptNameWithNumberByKey('commission', $commission->number)
        ])
        ->setOption('orientation', 'Landscape');
        return $pdf;
    }
}
