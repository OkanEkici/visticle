<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Order_Document extends Model
{
    protected $connection = 'tenant';
    
    protected $fillable = ['fk_order_id','filename','fk_order_document_type_id'];

    public function order() {
        return $this->belongsTo(Order::class, 'fk_order_id');
    }

    public function type() {
        return $this->belongsTo(Order_Document_Type::class, 'fk_order_document_type_id');
    }

    public function attributes() {
        return $this->hasMany(Order_Document_Attribute::class, 'fk_order_document_id');
    }
}
