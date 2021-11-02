<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Provider_Config_Shipment extends Model
{
    protected $connection = 'tenant';

    public function shipment() {
        return $this->belongsTo(Config_Shipment::class, 'fk_shipment_id');
    }
}
