<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_invoice_id', 'fk_config_payment_id', 'payment_date', 'payment_amount', 'fk_order_id'];

    public function invoice() {
        return $this->belongsTo(Invoice::class, 'fk_invoice_id');
    }

    public function order() {
        return $this->belongtsTo(Order::class, 'fk_order_id');
    }
}
