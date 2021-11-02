<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Config_Shipment extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['shipment_key', 'active'];

    public function providerConfigs() {
        return $this->hasMany(Provider_Config_Shipment::class, 'fk_shipment_id');
    }

    public function attributes() {
        return $this->hasMany(Config_Shipment_Attribute::class, 'fk_shipment_id');
    }
}
