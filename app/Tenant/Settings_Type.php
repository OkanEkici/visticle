<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Settings_Type extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'description'];

    public function settings() {
        return $this->hasMany(Setting::class, 'fk_settings_type_id');
    }
}
