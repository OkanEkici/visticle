<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderVoucher extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_order_id', 'fk_voucher_id', 'code', 'for', 'type', 'value', 'cart_price_limit','cart_price_min','unique_useable'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    public function voucher() {
        return $this->belongsTo(Voucher::class, 'fk_voucher_id');
    }

    public function articles() {
        return $this->hasMany(OrderVoucherArticle::class, 'voucher_id');
    }
    public function categories() {
        return $this->hasMany(OrderVoucherCategory::class, 'voucher_id');
    }
    
}
