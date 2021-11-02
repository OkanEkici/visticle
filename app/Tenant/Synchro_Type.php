<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Synchro_Type extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['name', 'description', 'key'];

    public function synchros() {
        return $this->hasMany(Synchro::class, 'fk_synchro_type_id');
    }
}
