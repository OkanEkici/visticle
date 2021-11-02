<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Provider_Config_Payment extends Model
{
    protected $connection = 'tenant';

    public function payment() {
        return $this->belongsTo(Config_Payment::class, 'fk_payment_id');
    }
}
