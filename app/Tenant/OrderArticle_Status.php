<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class OrderArticle_Status extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['description'];

    public function articles() {
        return $this->hasMany(OrderArticle::class, 'fk_orderarticle_status_id');
    }
}
