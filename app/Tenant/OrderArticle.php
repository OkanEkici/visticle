<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderArticle extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_id', 'fk_article_variation_id', 'fk_order_id', 'fk_orderarticle_status_id', 'quantity', 'price', 'packed', 'tax','returned'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    public function status() {
        return $this->belongsTo(OrderArticle_Status::class, 'fk_orderarticle_status_id');
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function variation() {
        return $this->belongsTo(Article_Variation::class, 'fk_article_variation_id');
    }

    public function attributes() 
    { return $this->hasMany(OrderArticle_Attribute::class, 'fk_orderarticle_id'); }
}
