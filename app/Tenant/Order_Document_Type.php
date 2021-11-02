<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order_Document_Type extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'description'];

    public function documents() {
        return $this->hasMany(Order_Document::class, 'fk_order_document_type_id');
    }
}
