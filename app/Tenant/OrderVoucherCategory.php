<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderVoucherCategory extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['category_id', 'voucher_id'];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function voucher() {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
