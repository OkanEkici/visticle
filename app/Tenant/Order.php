<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['created_at', 'updated_at', 'fk_provider_id', 'fk_order_status_id', 'fk_config_shipment_id', 'fk_config_payment_id', 'shipment_price', 'voucher_price','number','za_number','text'];

    public function status(){return $this->belongsTo(Order_Status::class, 'fk_order_status_id');}
    public function payment() { return $this->belongsTo(Config_Payment::class, 'fk_config_payment_id');}
    public function shipment() {return $this->belongsTo(Config_Shipment::class, 'fk_config_shipment_id'); }
    public function attributes() { return $this->hasMany(Order_Attribute::class, 'fk_order_id');}
    public function invoices() { return $this->hasMany(Invoice::class, 'fk_order_id');}
    public function provider() {return $this->belongsTo(Provider::class, 'fk_provider_id');}
    public function articles() { return $this->hasMany(OrderArticle::class, 'fk_order_id');}
    public function documents() { return $this->hasMany(Order_Document::class, 'fk_order_id');}
    public function payments() { return $this->hasMany(Payment::class, 'fk_order_id');}
    public function commissions() {
        return $this->belongsToMany(Commission::class, 'commission_orders', 'fk_order_id', 'fk_commission_id')->withPivot('fk_commissionorder_status_id');
    }

    public function getOrderNameBySetting() {
        return Setting::getReceiptNameWithNumberByKey('order', $this->number);
    }

    public function getShipmentAddress() {
        return $this->attributes()->where('name', 'LIKE', 'shippingaddress%')->get();
    }

    public function getShipmentAddressByKey(string $key) {
        return $this->attributes()->where('name', '=', 'shippingaddress_'.$key)->first()
        ? $this->attributes()->where('name', '=', 'shippingaddress_'.$key)->first()->value
        : '';
    }

    public function getBillingAddressByKey(string $key) {
        return $this->attributes()->where('name', '=', 'billingaddress_'.$key)->first()
        ? $this->attributes()->where('name', '=', 'billingaddress_'.$key)->first()->value
        : '';
    }

    public function getBillingAddress() { return $this->attributes()->where('name', 'LIKE', 'billingaddress%')->get(); }
    public function isShippingLikeBilling() { return $this->attributes()->where('name', '=', 'billingasshipping')->get()->where('value', '=', 'true')->first(); }
    public function getAddressKeys() { return ['gender', 'vorname', 'nachname', 'street', 'postcode', 'city', 'region', 'phone_number', 'email']; }

    public function canBeCancelled() { return (in_array($this->status()->first()->key, ['open','declined','shipped', 'awaiting_payment','awaiting_fulfillment','awaiting_shipment','partially_shipped'])); }
    public function canBeCompleted(){ return (!in_array($this->status()->first()->key, ['completed','returned','partially_returned','cancelled','refunded', 'open',  'awaiting_payment'/*,'awaiting_shipment'*/])); }
    public function canBeRetoured(){ return (in_array($this->status()->first()->key, ['completed'/*,'shipped'*/,'partially_shipped','partially_returned'])); }

    public function isFulfilled() { $status = $this->status()->first(); $fulfilledStatus = ['shipped', 'completed']; return ($status) ? in_array($status->key, $fulfilledStatus) : false; }
    public function isFullyPaid(){ $status = $this->status()->first(); return ($status) ? $status->id == 3 : false; }
    public function isInReturn(){ $status = $this->status()->first(); return ($status) ? $status->id == 12 : false; }
    public function isReturned(){ $status = $this->status()->first(); return ($status) ? $status->id == 11 : false; }



    public function getPaymentType() {
        $payment = $this->payment()->first();
        return ($payment) ? $payment->payment_key : '';
    }

    public function getTitleForAttributeKey($key) {
        $attributes = [
            'email' => 'E-Mail',
            'phone_number' => 'Telefon',
        ];

        return $attributes[$key];
    }



    public function getAllTaxPricesByKey($Tenant_type=false,$Doc_Datas = false)
    {
        $taxes = [];
        if($Doc_Datas){ $eans = [];foreach($Doc_Datas as $ean => $quantity){$eans[]=$ean; } }


        foreach($this->articles()->get() as $orderArticle)
        {
            if($Doc_Datas)
            {   $mainArticle = $orderArticle->article()->first();
                $varArticle = $orderArticle->variation()->first();
                if($mainArticle == null || $varArticle == null) {continue;}
                $org_ean = $varArticle->getEan();
                if($Doc_Datas[$org_ean] <= 0 || !in_array($org_ean,$eans)){continue;}
            }
            if(!isset($taxes[$orderArticle->tax])) { $taxes[$orderArticle->tax] = 0;}
            if(!$Tenant_type || ($Tenant_type && $Tenant_type!='vstcl-industry') )
            {$taxes[$orderArticle->tax] += ($orderArticle->price / 100 * $orderArticle->quantity) / ($orderArticle->tax + 100) * $orderArticle->tax;}
            else{$taxes[$orderArticle->tax] += ($orderArticle->price / 100 * $orderArticle->quantity) / (100) * $orderArticle->tax;}
        }

        $taxCount = count($taxes);
        $shipmentPricePerTax = 0;

        //if(!$Tenant_type || ($Tenant_type && $Tenant_type!='vstcl-industry') ){
            $shipmentTax = $this->getShippingTax($Tenant_type);
            if($taxCount == 0) { $shipmentPricePerTax = $shipmentTax; }
            else { $shipmentPricePerTax = $shipmentTax / $taxCount; }
            foreach($taxes as $taxKey => $tax)
            { $taxes[$taxKey] += $shipmentPricePerTax; }
        //}

        if(!$Doc_Datas)
        {   $voucherTax = $this->getVoucherTax();
            if($taxCount == 0) { $voucherPricePerTax = $voucherTax; }
            else { $voucherPricePerTax = $voucherTax / $taxCount; }
            foreach($taxes as $taxKey => $tax) { $taxes[$taxKey] -= $voucherPricePerTax; }
        }
        return $taxes;
    }

    public function getShippingTax($Tenant_type=false)
    {
        if($Tenant_type && $Tenant_type=='vstcl-industry' )
        {
            //return $this->getShipmentPrice() / 100 * 16;
            return $this->getShipmentPrice() / 100 * \App\Helpers\VAT::getVAT();// 04.01.2021 Tanju Özsoy
        }

        //return $this->getShipmentPrice() / 116 * 16;
        return $this->getShipmentPrice() / (100+\App\Helpers\VAT::getVAT()) * \App\Helpers\VAT::getVAT();
    }

    public function getOrderVoucherPrice() {
        $voucherPrice = ($this->voucher_price) ? $this->voucher_price : 0;
        return $voucherPrice;
    }

    public function getVoucherTax() {
        $voucherPrice = ($this->voucher_price) ? $this->voucher_price : 0;
        //return $voucherPrice / 116 * 16;
        return $voucherPrice / (100+\App\Helpers\VAT::getVAT()) * \App\Helpers\VAT::getVAT();//04.01.2021
    }


    public function getPriceWithTaxes($Doc_Datas = false) {
        $totalPrice = 0;
        if($Doc_Datas){ $eans = [];foreach($Doc_Datas as $ean => $quantity){$eans[]=$ean; } }
        foreach($this->articles()->get() as $orderArticle)
        {   if($Doc_Datas)
            {   $mainArticle = $orderArticle->article()->first();
                $varArticle = $orderArticle->variation()->first();
                if($mainArticle == null || $varArticle == null) {continue;}
                $org_ean = $varArticle->getEan();
                if($Doc_Datas[$org_ean] <= 0 || !in_array($org_ean,$eans)){continue;}
            }
            $totalPrice += $orderArticle->price * $orderArticle->quantity;
        }
        return $totalPrice / 100;
    }

    public function getShipmentPrice() {
        $shipment_price = $this->shipment_price;
        $totalPrice = 0;
        if($shipment_price && is_int((int)$shipment_price)) {
            $totalPrice += (int)$shipment_price / 100;
        }
        return $totalPrice;
    }

    public function getFullPrice_ind()
    {   $Tenant_type = config()->get('tenant.tenant_type');
        $shipment_price = $this->shipment_price;
        $totalPrice = 0;
        foreach($this->articles()->get() as $orderArticle)
        { $totalPrice += $orderArticle->price * $orderArticle->quantity; }
        if($shipment_price && is_int((int)$shipment_price))
        { $totalPrice += (int)$shipment_price; }
        $GesamtTax = 0; foreach ($this->getAllTaxPricesByKey($Tenant_type) as $key => $value)
        { $GesamtTax += $value*100; }
        $voucherPrice = ($this->voucher_price) ? $this->voucher_price : 0;
        return $totalPrice + $GesamtTax - ($voucherPrice*100);
    }

    public function getFullPrice($Doc_Datas = false) {
        $shipment_price = $this->shipment_price;
        $totalPrice = 0;
        if($Doc_Datas){ $eans = [];foreach($Doc_Datas as $ean => $quantity){$eans[]=$ean; } }

        foreach($this->articles()->get() as $orderArticle)
        {   if($Doc_Datas)
            {   $mainArticle = $orderArticle->article()->first();
                $varArticle = $orderArticle->variation()->first();
                if($mainArticle == null || $varArticle == null) {continue;}
                $org_ean = $varArticle->getEan();
                if($Doc_Datas[$org_ean] <= 0 || !in_array($org_ean,$eans)){continue;}
            }
            $totalPrice += $orderArticle->price * $orderArticle->quantity;
        }
        if($shipment_price && is_int((int)$shipment_price))
        { $totalPrice += (int)$shipment_price; }

        return $totalPrice - ((float)$this->getOrderVoucherPrice()*100 );
    }

    public function getFormattedPrice($price) {
        return str_replace('.',',', number_format((float)str_replace(',','.',$price), 2, '.', '')) . '€';
    }

    public function getPayedPrice() {
        $payments = $this->payments();

        $alreadyPayed = 0;
        //check if payment fits in price to pay
        foreach($payments->get() as $payment) {
             $alreadyPayed = (float)$alreadyPayed + (float)$payment->payment_amount;
        }

        return $alreadyPayed;
    }

    public function getOpenPrice($totalPrice=false)
    {   $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry'){$totalPrice = $this->getFullPrice_ind() / 100;}
        else{$totalPrice = $this->getFullPrice() / 100;}

        return (float)(sprintf("%01.2f", ($totalPrice - $this->getPayedPrice()) ));
    }

    public function setStatus(string $status) {
        $status = Order_Status::where('key', '=', $status)->first();
        $this->fk_order_status_id = $status->id;
        $this->save();
    }

    public function updateOrCreateAttribute($name, $value){
        return Order_Attribute::updateOrCreate(
            [
                'fk_order_id' => $this->id,
                'name' => $name
            ],
            [
                'value' =>  $value
            ]
        );
    }

    public static function sidenavConfig()
    {
        $sidenavConfig[]=['name' => 'Auftragsverwaltung', 'route' => '/orders', 'iconClass' => 'fas fa-table' ];
        $sidenavConfig[]=['name' => 'Kommissionierlisten', 'route' => '/commissions', 'iconClass' => 'fas fa-receipt' ];
        $sidenavConfig[]=['name' => 'Lieferscheine', 'route' => '/delivery_notes', 'iconClass' => 'fas fa-truck' ];

        $Tenant_type = config()->get('tenant.tenant_type');
        if($Tenant_type=='vstcl-industry')
        { $sidenavConfig[]=['name' => 'Auftragsbestätigungen', 'route' => '/order_confirmations', 'iconClass' => 'fas fa-receipt' ]; }

        $sidenavConfig[]=['name' => 'Rechnungen', 'route' => '/invoices', 'iconClass' => 'fas fa-file-invoice-dollar' ];
        $sidenavConfig[]=['name' => 'Retourenverwaltung', 'route' => '/retours', 'iconClass' => 'fas fa-undo-alt' ];
        $sidenavConfig[]=['name' => 'Gutschriften', 'route' => '/credit_notes', 'iconClass' => 'fas fa-hand-holding-usd' ];
        $sidenavConfig[]=['name' => 'OPOS - Zahlungseingänge buchen', 'route' => '/opos', 'iconClass' => 'fas fa-money-check-alt' ];
        $sidenavConfig[]=['name' => 'Konflikte', 'route' => '/conflicts', 'iconClass' => 'fas fa-exclamation' ];
        $sidenavConfig[]=['name' => 'Teillieferungen', 'route' => '/partial_shipments', 'iconClass' => 'fas fa-truck-loading' ];
        $sidenavConfig[]=['name' => 'Statistiken', 'route' => '/statistics', 'iconClass' => 'fas fa-chart-bar' ];

        return $sidenavConfig;
    }
}
