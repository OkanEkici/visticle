<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order_Document_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['id','fk_order_document_id','name', 'value'];

    public function order() {
        return $this->belongsTo(Order_Document::class, 'fk_order_document_id');
    }
}
