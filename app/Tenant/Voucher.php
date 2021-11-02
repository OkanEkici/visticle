<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{   protected $connection = 'tenant';
    
    protected $fillable = [
        'valid_from'
        ,'valid_to'
        ,'code'
        ,'for' // Global / category / article
        ,'type'
        ,'value'        
        ,'cart_price_limit','cart_price_min','unique_useable'
        ,'active'
    ];

    public function articles() {
        return $this->belongsToMany(Article::class, 'voucher_articles');
    }
    public function categories() {
        return $this->belongsToMany(Category::class, 'voucher_categories');
    }

}
