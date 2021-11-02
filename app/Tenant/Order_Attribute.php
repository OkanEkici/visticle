<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_order_id', 'name', 'value'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }
}
