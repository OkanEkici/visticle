<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['sysnumber', 'number'];

    public function orders() {
        return $this->belongsToMany(Order::class, 'commission_orders', 'fk_commission_id', 'fk_order_id')->withPivot('fk_commissionorder_status_id');
    }
}
