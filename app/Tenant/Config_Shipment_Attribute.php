<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Config_Shipment_Attribute extends Model
{
    protected $connection = 'tenant';

    public function shipment() {
        return $this->belongsTo(Config_Shipment::class, 'fk_shipment_id');
    }
}
