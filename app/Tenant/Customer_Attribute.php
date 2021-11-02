<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Customer_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_customer_id', 'name', 'value'];

    public function customer() {
        return $this->belongsTo(Customer::class, 'fk_customer_id');
    }
}
