<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Settings_Attribute extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_setting_id', 'name', 'value'];

    public function setting() {
        return $this->belongsTo(Setting::class, 'fk_setting_id');
    } 
}
