<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class CommissionOrder extends Model
{
    protected $connection = 'tenant';

    public function status() {
        return $this->belongsTo(CommissionOrder_Status::class, 'fk_commissionorder_status_id');
    }

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    public function commission() {
        return $this->belongsTo(Commission::class, 'fk_commission_id');
    }
}
