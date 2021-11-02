<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Config_Payment_Attribute extends Model
{
    protected $connection = 'tenant';

    public function payment() {
        return $this->belongsTo(Config_Payment::class, 'fk_payment_id');
    }
}
