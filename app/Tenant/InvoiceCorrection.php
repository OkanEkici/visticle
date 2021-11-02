<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class InvoiceCorrection extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_order_id', 'number'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    
}
