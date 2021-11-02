<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class TenantUser_Config extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_tenant_user_id', 'name', 'value'];

    public function user() {
        return $this->belongsTo(TenantUser::class, 'fk_tenant_user_id');
    }
}
