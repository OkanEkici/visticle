<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Provider_Config extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_provider_id'];

    public function provider() {
        return $this->belongsTo(Provider::class, 'fk_provider_id');
    }

    public function attributes() {
        return $this->hasMany(Provider_Config_Attribute::class, 'fk_provider_config_id');
    }
}
