<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderVoucherArticle extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['article_id', 'voucher_id'];

    public function article() {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function voucher() {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
