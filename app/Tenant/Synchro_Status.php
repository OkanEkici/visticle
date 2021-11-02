<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Synchro_Status extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['description'];

    public function synchros() {
        return $this->hasMany(Synchro::class, 'fk_synchro_status_id');
    }
}
