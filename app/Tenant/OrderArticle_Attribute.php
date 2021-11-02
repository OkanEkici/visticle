<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderArticle_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id','name', 'value', 'fk_orderarticle_id'];

    public function orderArticle() 
    { return $this->belongsTo(OrderArticle::class, 'id'); }
}
