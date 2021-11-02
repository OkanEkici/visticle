<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Config_Payment extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['payment_key', 'active'];

    public function providerConfigs() {
        return $this->hasMany(Provider_Config_Payment::class, 'fk_payment_id');
    }

    public function attributes() {
        return $this->hasMany(Config_Payment_Attribute::class, 'fk_payment_id');
    }
}
