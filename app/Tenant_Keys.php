<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tenant_Keys extends Model
{
    protected $fillable = ['fk_tenant_id', 'provider_id', 'access_key', 'active'];

    public function tenant() {
        return $this->belongsTo(Tenant::class, 'fk_tenant_id');
    }
}
